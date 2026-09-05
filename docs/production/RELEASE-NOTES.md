# PMOIS v2 — Release Notes (Release 1.0.0-UAT)

**Baseline:** M0 R6 Design Freeze • M1–M8 ผ่าน CTO Review ทั้งหมด • M9 hardening ครบ
**Status:** ✅ **M9 CTO-Approved Baseline** — รอ CEO UAT; Production Release Tag / Production Baseline / Production Deployment จะดำเนินการหลัง UAT ผ่าน
**Date:** 2026-09-06

---

## ฟีเจอร์ครบตาม Roadmap M1–M8

### Foundation (M1/M2)
- LINE Login only (fail-closed, invitation/claim flow, PMOIS session) — CTO Constraint
- Project CRUD + Workspace/Parent hierarchy (move/parent/promote, structure history, ID immutability)
- Milestone Management (open/close, CTO-only authority)
- Team Registry (human) + AI Registry (Provider ⟂ Agent) + AI Assignment
- Governance: templates, versioning (auto-supersede), auto-binding on project creation, policies (Review/Delivery/Approval), working instructions
- Project Templates + Workspace Defaults → CEO กรอกข้อมูลน้อยที่สุด
- Registries: Technology Stack, Environments (no secrets), Dependencies (acyclic graph), Releases (state machine)
- Repository Registry (GitLab only, manual registration)
- Profile Completeness (แยกจาก Progress)

### Dashboards & Analytics (M2/M7)
- Workspace/Portfolio/Project/Parent dashboards, Timeline, Recent Activities,
  Progress/Health Summaries, Portfolio Statistics, KPI Dashboard (on-time rate, review turnaround),
  Productivity Metrics (CTO/Dev, AI-attributed), At-risk projects with signals, Dependency analytics, Reports

### Project Management (M3)
- Revision → CTO Review → Commit workflow (state machine + PMO timeline auto-update)
- Deployment Tracking (state machine), Activity History

### Knowledge Center (M6)
- Business Rules / Known Issues / Risk Register / Future Enhancements registries
- ADR (decision_registers), Unified Search, Knowledge Timeline, Management UI

### Automation Center (M8)
- Automation Queue + Background worker (cron/CLI), Workflows (timeline/project update auto),
- Telegram Automation, GitLab sync (read-only), AI Dev Auto dispatch

### API Platform (M5)
- Bearer + session auth, RBAC permissions, Scope management (backward compatible),
- Rate limiting, Audit API, Metrics, Health check, OpenAPI spec

### Constraints (mandatory)
LINE Login only • GitLab only • Telegram only — ตามมติ CTO ทุก milestone

---

## ข้อจำกัดที่ประกาศชัด (not defects)

1. Rate limiter + automation worker รองรับ single-node
2. Analytics เป็น real-time aggregate (ยังไม่มี pre-materialized store)
3. Revision submit ผ่าน API เท่านั้น (Web UI ส่งได้ผ่านฟอร์มที่เรียก API)
4. Future Enhancements ที่บันทึกไว้ตามมติ CTO: IdentityVerificationService, Unified Timeline,
   Context Exports (M8/M9), Predictive Analytics, Extended Health Checks (M9)

## การอัปเกรดจาก UAT → Production

ใช้ deployment package เดียวกัน + ย้าย backup ของ UAT (ถ้าต้องการเก็บข้อมูล) หรือ install ใหม่ตาม Installation Guide
