# PMOIS v2 — Performance Review (M9)

**ขอบเขต:** ทบทวน performance จากการออกแบบ + การทดสอบจริงบน MySQL 5.7.36 / PHP 8.1 (single node)
**Date:** 2026-09-06

---

## 1. ผลการทดสอบจริง (สภาพแวดล้อม validation)

| รายการ | ผล |
|---|---|
| Full test suite (177 tests) บน installed DB | 1.3–2.2 วินาที รวมทุก integration flow |
| Consolidated installer (45 ตาราง + seeds) | < 2 วินาที |
| mysqldump --single-transaction (ฐานว่าง + seed) | ~1 วินาที, ไฟล์ ~90KB |
| Backup → drop → restore → verify | ผ่านครบ |

## 2. Index coverage (ตรวจแล้วครบตาม migrations)

- ทุกตารางมี PK + index ตาม access pattern หลัก (workspace_id, project_id, status)
- Fulltext: `knowledge_articles` (0024)
- Unique keys: state hash, session hash, token hash, provider codes, edge uniqueness — รองรับ idempotency/one-time semantics ด้วย index ตรง
- จุดที่ตรวจแล้วว่าไม่มี full scan ใน hot path: dashboard/analytics queries ผูก workspace_id ทุก query

## 3. ปัจจัยที่ต้องเฝ้าดูใน production

| ประเด็น | สถานะ | ข้อแนะนำ |
|---|---|---|
| Analytics/dashboards เป็น aggregate real-time | อ่านตรงจาก tables | ถ้าข้อมูล > 10k projects ค่อยพิจารณา cache/cron materialize (M0: ไม่เพิ่ม infra ก่อนจำเป็น) |
| Rate limiter in-memory per worker | จำกัดที่ single-node | ย้าย shared store เมื่อ scale out |
| Automation worker single instance | cron ทุกนาที | ห้ามรันหลาย instance พร้อมกันโดยไม่เพิ่ม locking |
| Session/state tables เติบโต | cron cleanup รายวัน (Operations Guide §1) | ตรวจขนาดตารางรายเดือน |
| audit_trails โตเร็วตามการใช้งาน | ไม่มี purge (นโยบาย Q07) | กำหนด retention ก่อนข้อมูล > หลักล้านแถว |

## 4. สรุป

ไม่พบ bottleneck สำหรับขนาดองค์กรภายใน (ผู้ใช้สิบ–ร้อย, projects ร้อย–พัน) บน single node
พร้อม UAT — ตัวชี้วัดจริงใน UAT ควรเก็บ: response time ของ dashboards, queue latency, notification failure rate
