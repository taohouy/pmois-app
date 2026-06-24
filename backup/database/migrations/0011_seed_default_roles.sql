-- Phase 0 / Foundation — seed default roles
-- ตัวอย่าง role เริ่มต้น (เป็น data ปรับแก้ได้ทีหลังโดยไม่กระทบ schema)
INSERT INTO roles (code, name, description) VALUES
('ADMIN',        'Admin',        'สิทธิ์เต็มในระดับ workspace/project'),
('MEMBER',       'Member',       'สิทธิ์ทำงานพื้นฐาน สร้าง/แก้ไขเนื้อหาของตัวเอง'),
('VIEWER',       'Viewer',       'สิทธิ์อ่านอย่างเดียว ไม่มี create/update/approve'),
('CTO',          'CTO',          'สิทธิ์ระดับสูง เน้น Governance/RFC/Decision'),
('SENIOR_DEV',   'Senior Dev',   'สิทธิ์งานเทคนิค ไม่มีสิทธิ์ review/approve'),
('PMO_REVIEWER', 'PMO Reviewer', 'สิทธิ์ตรวจ/อนุมัติ ไม่ใช่ผู้สร้างเนื้อหา');
