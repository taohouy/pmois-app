<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Infrastructure\Http\CurlHttpClient;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\RequestFactory;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\StreamFactory;

/**
 * CurlHttpClientTest — M9 UAT Runtime Fix Revision 4
 *
 * Root cause ที่แก้: เดิม CurlHttpClient ตัดเอา CURLINFO_HEADER_SIZE ไบต์แรกของ
 * curl_exec() body มา parse เป็น header (ทั้งที่ CURLOPT_RETURNTRANSFER คืน "body เท่านั้น")
 * → JSON body จาก LINE ({"access_token":...}) ถูก parse เป็น header
 * → "Header name must be an RFC 7230 compatible string" throw ทุกครั้งใน production
 *   (unit test อื่นไม่จับ เพราะใช้ fake HTTP client)
 *
 * การทดสอบรันผ่าน HTTP จริง: local HTTP server (php child process) + cURL จริง
 * ครอบคลุม: JSON response (เคสที่เคยพัง), status line skip, invalid header line skip,
 * multiple Set-Cookie, interim 100-continue, POST form body
 */
final class CurlHttpClientTest extends TestCase
{
    /** @var resource|null */
    private $proc = null;
    private string $tmpFile = '';

    protected function tearDown(): void
    {
        if ($this->proc !== null) {
            proc_terminate($this->proc);
            proc_close($this->proc);
            $this->proc = null;
        }
        if ($this->tmpFile !== '' && file_exists($this->tmpFile)) {
            @unlink($this->tmpFile);
        }
    }

    /**
     * Spawn local HTTP server (php child) ที่รอรับ 1 request แล้วตอบ $rawResponse
     *
     * @return int port ที่ server bind
     */
    private function startServer(string $rawResponse): int
    {
        $this->tmpFile = tempnam(sys_get_temp_dir(), 'pmois_raw_') . '.txt';
        file_put_contents($this->tmpFile, $rawResponse);

        $childScript = <<<'PHP'
$rawFile = $argv[1];
$raw = file_get_contents($rawFile);
$server = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
if ($server === false) { fwrite(STDERR, "bind failed: $errstr"); exit(1); }
$name = stream_socket_get_name($server, false);
$port = substr($name, strrpos($name, ':') + 1);
fwrite(STDOUT, "PORT:$port\n");
flush();
$conn = stream_socket_accept($server, 30);
if ($conn === false) { exit(1); }
$req = '';
while (!str_contains($req, "\r\n\r\n")) {
    $chunk = fread($conn, 8192);
    if ($chunk === false || $chunk === '') break;
    $req .= $chunk;
}
if (preg_match('/Content-Length: (\d+)/i', $req, $m)) {
    $bodyLen = (int)$m[1];
    $headerEnd = (int)(strpos($req, "\r\n\r\n") + 4);
    while (strlen($req) - $headerEnd < $bodyLen) {
        $chunk = fread($conn, 8192);
        if ($chunk === false || $chunk === '') break;
        $req .= $chunk;
    }
}
fwrite($conn, $raw);
fclose($conn);
fclose($server);
PHP;

        $phpBinary = PHP_BINARY;
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $this->proc = proc_open([$phpBinary, '-r', $childScript, $this->tmpFile], $descriptors, $pipes);
        $this->assertIsResource($this->proc, 'proc_open failed');

        // อ่าน port ที่ child bind ได้
        stream_set_blocking($pipes[1], true);
        $portLine = '';
        $deadline = microtime(true) + 5.0;
        while (microtime(true) < $deadline && !str_contains($portLine, 'PORT:')) {
            $chunk = fread($pipes[1], 256);
            if ($chunk !== false && $chunk !== '') {
                $portLine .= $chunk;
            } else {
                usleep(10000);
            }
        }
        $this->assertStringContainsString('PORT:', $portLine, "child server did not report port: $portLine");
        $port = (int) trim(explode('PORT:', $portLine)[1]);

        return $port;
    }

    private function client(): CurlHttpClient
    {
        return new CurlHttpClient(new ResponseFactory());
    }

    // ===== เคสที่เคยพังใน production: JSON body ถูก parse เป็น header =====

    public function testJsonResponseParsesCorrectlyNotAsHeaders(): void
    {
        // นี่คือ response จริงรูปแบบเดียวกับ LINE token endpoint —
        // โค้ดเดิมจะ throw "Header name must be an RFC 7230 compatible string" กับ response นี้
        $json = '{"access_token":"eyJhbGciOiJIUz","refresh_token":"r1","id_token":"eyJhbGciOiJIUz","expires_in":2592000,"scope":"openid profile"}';
        $raw = "HTTP/1.1 200 OK\r\nServer: nginx\r\nContent-Type: application/json\r\nContent-Length: " . strlen($json) . "\r\n\r\n" . $json;
        $port = $this->startServer($raw);

        $request = (new RequestFactory())
            ->createRequest('POST', "http://127.0.0.1:{$port}/oauth2/v2.1/token")
            ->withHeader('Content-Type', 'application/x-www-form-urlencoded')
            ->withBody((new StreamFactory())->createStream('grant_type=authorization_code&code=abc'));

        $response = $this->client()->sendRequest($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('application/json', $response->getHeaderLine('Content-Type'));
        $body = json_decode((string) $response->getBody(), true);
        $this->assertIsArray($body);
        $this->assertSame('eyJhbGciOiJIUz', $body['access_token']);
        $this->assertSame('eyJhbGciOiJIUz', $body['id_token']);
    }

    public function testVerifyEndpointStyleResponseParses(): void
    {
        // response รูปแบบ LINE verify endpoint (claims)
        $json = '{"iss":"https://access.line.me","sub":"U123","aud":"ch1","exp":' . (time() + 600) . ',"iat":' . time() . ',"name":"Test"}';
        $raw = "HTTP/1.1 200 OK\r\nContent-Type: application/json\r\nContent-Length: " . strlen($json) . "\r\n\r\n" . $json;
        $port = $this->startServer($raw);

        $request = (new RequestFactory())
            ->createRequest('POST', "http://127.0.0.1:{$port}/oauth2/v2.1/verify")
            ->withBody((new StreamFactory())->createStream('id_token=x&client_id=ch1'));

        $response = $this->client()->sendRequest($request);

        $this->assertSame(200, $response->getStatusCode());
        $body = json_decode((string) $response->getBody(), true);
        $this->assertSame('U123', $body['sub']);
        $this->assertSame('https://access.line.me', $body['iss']);
    }

    // ===== parseHeaderLine — unit level =====

    public function testStatusLineIsNotHeader(): void
    {
        $this->assertNull(CurlHttpClient::parseHeaderLine("HTTP/1.1 200 OK\r\n"));
        $this->assertNull(CurlHttpClient::parseHeaderLine("HTTP/1.1 100 Continue\r\n"));
        $this->assertNull(CurlHttpClient::parseHeaderLine("HTTP/2 200\r\n"));
    }

    public function testJsonBodyLineIsNotHeader(): void
    {
        // บรรทัดแรกของ JSON body — เดิมโค้ด parse แล้ว throw RFC 7230; ต้อง skip ไม่ throw
        $this->assertNull(CurlHttpClient::parseHeaderLine('{"access_token":"eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9"}'));
        $this->assertNull(CurlHttpClient::parseHeaderLine('{"error":"invalid_grant"}'));
    }

    public function testValidHeaderLineParsed(): void
    {
        $this->assertSame(['Content-Type', 'application/json'], CurlHttpClient::parseHeaderLine("Content-Type: application/json\r\n"));
        $this->assertSame(['Set-Cookie', 'a=b; Path=/; HttpOnly'], CurlHttpClient::parseHeaderLine("Set-Cookie: a=b; Path=/; HttpOnly\r\n"));
        $this->assertSame(['x-request-id', 'abc123'], CurlHttpClient::parseHeaderLine("x-request-id: abc123\r\n"));
    }

    public function testMalformedHeaderLineSkippedNotThrown(): void
    {
        // ชื่อ header มีอักขระต้องห้าม — ต้องข้าม (return null) ไม่ใช่ throw
        $this->assertNull(CurlHttpClient::parseHeaderLine('{"access_token": "x"}'));
        $this->assertNull(CurlHttpClient::parseHeaderLine('Bad Header Name: value'));
        $this->assertNull(CurlHttpClient::parseHeaderLine(''));
        $this->assertNull(CurlHttpClient::parseHeaderLine('no-colon-line'));
    }

    // ===== runtime: multiple Set-Cookie + interim 100-continue =====

    public function testMultipleSetCookieHeadersPreserved(): void
    {
        $body = '{"ok":true}';
        $raw = "HTTP/1.1 200 OK\r\nContent-Type: application/json\r\n"
            . "Set-Cookie: first=1; Path=/; HttpOnly\r\n"
            . "Set-Cookie: second=2; Path=/; Secure\r\n"
            . "Content-Length: " . strlen($body) . "\r\n\r\n" . $body;
        $port = $this->startServer($raw);

        $request = (new RequestFactory())->createRequest('GET', "http://127.0.0.1:{$port}/test");
        $response = $this->client()->sendRequest($request);

        $cookies = $response->getHeader('Set-Cookie');
        $this->assertCount(2, $cookies, 'Set-Cookie 2 บรรทัดต้องถูกเก็บแยกกัน');
        $this->assertSame('first=1; Path=/; HttpOnly', $cookies[0]);
        $this->assertSame('second=2; Path=/; Secure', $cookies[1]);
    }

    public function testInterim100ContinueDoesNotBreakParsing(): void
    {
        $body = '{"status":"ok"}';
        // interim response + final response ใน stream เดียว (เกิดได้กับ POST + Expect: 100-continue)
        $raw = "HTTP/1.1 100 Continue\r\n\r\n"
            . "HTTP/1.1 200 OK\r\nContent-Type: application/json\r\n"
            . "Content-Length: " . strlen($body) . "\r\n\r\n" . $body;
        $port = $this->startServer($raw);

        $request = (new RequestFactory())
            ->createRequest('POST', "http://127.0.0.1:{$port}/verify")
            ->withBody((new StreamFactory())->createStream('id_token=x&client_id=y'));

        $response = $this->client()->sendRequest($request);

        $this->assertSame(200, $response->getStatusCode(), 'status ต้องเป็นของ final response ไม่ใช่ 100');
        $body = json_decode((string) $response->getBody(), true);
        $this->assertSame('ok', $body['status']);
    }
}
