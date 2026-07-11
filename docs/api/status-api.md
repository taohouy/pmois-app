# PMOIS Status API v1.0

## Purpose

ใช้สำหรับให้แต่ละโครงการส่งสถานะเข้า PMO Internal System (PMOIS) เพื่อให้ PMO ใช้ติดตาม Portfolio

## Endpoint

```http
POST {PMO_API_URL}/v1/projects/status
```

ตัวอย่าง

```http
POST https://pmo.jaideedigital.com/api/v1/projects/status
```

## Authentication

ใช้ Bearer Token

```http
Authorization: Bearer {PMO_PROJECT_TOKEN}
Content-Type: application/json
```

## Request Body

```json
{
  "project_id": 5,
  "progress": 75,
  "current": "Core modules completed and under verification",
  "next": "Continue remaining module development and prepare UAT",
  "risk": "Some business rules may require clarification during testing",
  "blocker": "None"
}
```

## Required Fields

| Field      | Description                    |
| ---------- | ------------------------------ |
| project_id | Project ID จาก PMOIS           |
| progress   | ความคืบหน้าเป็นเปอร์เซ็นต์     |
| current    | สถานะปัจจุบัน                  |
| next       | งานถัดไป                       |
| risk       | ความเสี่ยง                     |
| blocker    | อุปสรรค หากไม่มีให้ระบุ `None` |

## cURL Example

```bash
curl -X POST "$PMO_API_URL/v1/projects/status" \
  -H "Authorization: Bearer $PMO_PROJECT_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "project_id": 5,
    "progress": 75,
    "current": "Core modules completed and under verification",
    "next": "Continue remaining module development and prepare UAT",
    "risk": "Some business rules may require clarification during testing",
    "blocker": "None"
  }'
```

## Expected Result

เมื่อส่งสำเร็จ PMOIS ต้องบันทึกสถานะของ Project และ PMO สามารถนำข้อมูลไปใช้ติดตาม Portfolio ได้

## Notes

* ห้าม Commit `PMO_PROJECT_TOKEN` เข้า Git
* ใช้ Token เฉพาะ Project นั้นเท่านั้น
* ถ้าไม่มี Risk หรือ Blocker ให้ใส่ `None`
