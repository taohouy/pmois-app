# PMOIS Project Integration Guide

## Required ENV

เพิ่มค่าเหล่านี้ใน `.env` ของโครงการ

```env
PMO_PROJECT_ID=5
PMO_API_URL=https://pmo.jaideedigital.com/api
PMO_PROJECT_TOKEN=xxxxxxxx
```

## Integration Steps

1. อ่านค่า `PMO_PROJECT_ID`, `PMO_API_URL`, `PMO_PROJECT_TOKEN` จาก `.env`
2. สร้าง Service หรือ Function สำหรับส่งสถานะโครงการเข้า PMOIS
3. เรียก `POST /v1/projects/status`
4. ส่งข้อมูลขั้นต่ำ ได้แก่

   * progress
   * current
   * next
   * risk
   * blocker
5. ทดสอบด้วยข้อมูลจริงของโครงการ
6. แจ้ง PMO เมื่อ Integration สำเร็จ

## When to Submit Status

ให้ส่งข้อมูลเมื่อมีเหตุการณ์ต่อไปนี้

* Milestone Complete
* Sprint Complete
* Production Deploy
* Risk เปลี่ยน
* Blocker เกิดขึ้นหรือถูกแก้ไข
* PMO / CTO Request

ไม่จำเป็นต้องส่งทุก Commit

## PHP Example

```php
<?php

function sendPmoStatus(array $status): bool
{
    $apiUrl = rtrim($_ENV['PMO_API_URL'], '/');
    $token = $_ENV['PMO_PROJECT_TOKEN'];
    $projectId = (int) $_ENV['PMO_PROJECT_ID'];

    $payload = [
        'project_id' => $projectId,
        'progress' => $status['progress'],
        'current' => $status['current'],
        'next' => $status['next'],
        'risk' => $status['risk'],
        'blocker' => $status['blocker'],
    ];

    $ch = curl_init($apiUrl . '/v1/projects/status');

    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if ($response === false) {
        error_log('PMOIS API Error: ' . curl_error($ch));
        curl_close($ch);
        return false;
    }

    curl_close($ch);

    if ($httpCode < 200 || $httpCode >= 300) {
        error_log('PMOIS API Failed: HTTP ' . $httpCode . ' Response: ' . $response);
        return false;
    }

    return true;
}
```

## Test Payload for SN Chemical POS

```json
{
  "project_id": 5,
  "progress": 75,
  "current": "SN Chemical POS is under active development with core modules completed",
  "next": "Continue remaining module development and prepare UAT",
  "risk": "Business rules for some POS and inventory workflows may require confirmation",
  "blocker": "None"
}
```

## Completion Criteria

ถือว่า Integration สำเร็จเมื่อ

* API ส่งข้อมูลสำเร็จ
* PMOIS บันทึกสถานะได้
* PMO ตรวจสอบข้อมูลในฐานข้อมูลแล้วพบข้อมูลถูกต้อง
* CTO แจ้งผลทดสอบกลับ PMO
