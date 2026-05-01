// ============================================================
// STEAMhives RMS v3.0 — Database Adapter
// Replaces localStorage with fetch() calls to /api/api.php
// All functions return Promises; the app awaits them.
// ============================================================

const API_BASE = './api/api.php';

// ── Low-level fetch helper ────────────────────────────────
async function apiCall(action, data = {}) {
  const res = await fetch(`${API_BASE}?action=${action}`, {
    method: 'POST',
    credentials: 'include',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ action, ...data })
  });
  const json = await res.json();
  if (!json.ok) throw new Error(json.error || 'API error');
  return json;
}

// ── In-memory cache (reduces round-trips) ─────────────────
const CACHE = {
  students: null, results: null, midResults: null,
  settings: null, pins: null,
  bust() { this.students=null; this.results=null; this.midResults=null; this.settings=null; this.pins=null; }
};

// ── AUTH ──────────────────────────────────────────────────
async function apiGetSchools()           { return (await apiCall('getSchools')).schools; }
async function apiLogin(schoolId, pass)  { return apiCall('login', { school_id: schoolId, password: pass }); }
async function apiLogout()               { return apiCall('logout'); }
async function apiRegister(coupon, name, pass) { return apiCall('register', { coupon_code: coupon, school_name: name, password: pass }); }

// ── SETTINGS ──────────────────────────────────────────────
async function apiGetSettings() {
  if (CACHE.settings) return CACHE.settings;
  const r = await apiCall('getSettings');
  // Normalise snake_case DB columns → camelCase
  const s = r.settings || {};
  CACHE.settings = {
    name:          s.name          || '',
    motto:         s.motto         || '',
    address:       s.address       || '',
    phone:         s.phone         || '',
    email:         s.email         || '',
    color1:        s.color1        || '#0a6640',
    color2:        s.color2        || '#c8960c',
    schoolLogo:    s.school_logo   || null,
    sigPrincipal:  s.sig_principal || null,
    headSignature: s.head_signature|| null,
    schoolStamp:   s.school_stamp  || null,
    classTeachers: s.class_teachers ? (typeof s.class_teachers==='string' ? JSON.parse(s.class_teachers) : s.class_teachers) : {},
    _school:       r.school || {}
  };
  return CACHE.settings;
}

async function apiSaveSettings(payload) {
  CACHE.settings = null;
  return apiCall('saveSettings', payload);
}

// ── STUDENTS ──────────────────────────────────────────────
async function apiGetStudents() {
  if (CACHE.students) return CACHE.students;
  const r = await apiCall('getStudents');
  // Normalise DB row → frontend shape (snake_case → camelCase not needed since
  // frontend was already using snake_case-ish keys; just return as-is)
  CACHE.students = r.students;
  return CACHE.students;
}

async function apiSaveStudent(student) {
  CACHE.students = null;
  return apiCall('saveStudent', student);
}

async function apiDeleteStudent(id) {
  CACHE.students = null; CACHE.results = null; CACHE.midResults = null;
  return apiCall('deleteStudent', { id });
}

// ── RESULTS ───────────────────────────────────────────────
async function apiGetResults() {
  if (CACHE.results) return CACHE.results;
  const r = await apiCall('getResults');
  // Map DB snake_case → camelCase for frontend compatibility
  CACHE.results = r.results.map(mapResultFromDB);
  return CACHE.results;
}

async function apiSaveResult(result) {
  CACHE.results = null;
  const r = await apiCall('saveResult', mapResultToDB(result));
  return r;
}

async function apiUpdatePositions(positions) {
  CACHE.results = null;
  return apiCall('updatePositions', { positions });
}

async function apiDeleteResult(id) {
  CACHE.results = null;
  return apiCall('deleteResult', { id });
}

// ── MID-TERM ──────────────────────────────────────────────
async function apiGetMidResults() {
  if (CACHE.midResults) return CACHE.midResults;
  const r = await apiCall('getMidResults');
  CACHE.midResults = r.results.map(mapMidFromDB);
  return CACHE.midResults;
}

async function apiSaveMidResult(result) {
  CACHE.midResults = null;
  return apiCall('saveMidResult', mapMidToDB(result));
}

// ── ATTENDANCE ────────────────────────────────────────────
async function apiGetAttendance(term, session, week) {
  const r = await apiCall('getAttendance', { term, session, week });
  // Convert rows → currentWeekData shape { 'student_<id>': {monday: ..., ...} }
  const weekData = {};
  for (const row of r.attendance) {
    const key = 'student_' + row.student_id;
    weekData[key] = {
      monday: row.monday || '', tuesday: row.tuesday || '',
      wednesday: row.wednesday || '', thursday: row.thursday || '', friday: row.friday || ''
    };
  }
  return weekData;
}

async function apiSaveAttendance(weekData, term, session, week) {
  const records = Object.entries(weekData).map(([key, days]) => ({
    student_id: key.replace('student_', ''),
    term, session, week, ...days
  }));
  return apiCall('saveAttendance', { records });
}

async function apiGetAttendanceAggregate(studentId, term, session) {
  return apiCall('getAttendanceAggregate', { student_id: studentId, term, session });
}

// ── PINS ──────────────────────────────────────────────────
async function apiGetPins() {
  if (CACHE.pins) return CACHE.pins;
  const r = await apiCall('getPins');
  CACHE.pins = r.pins;
  return CACHE.pins;
}

async function apiSavePin(pin) {
  CACHE.pins = null;
  return apiCall('savePin', pin);
}

async function apiRevokePin(pin) {
  CACHE.pins = null;
  return apiCall('revokePin', { pin });
}

// ── UPGRADE PLAN ──────────────────────────────────────────
async function apiUpgradePlan(couponCode) {
  CACHE.settings = null;
  return apiCall('upgradePlan', { couponCode });
}

// ── BACKUP / RESTORE ──────────────────────────────────────
async function apiBackupData()          { return apiCall('backupData'); }
async function apiRestoreData(payload)  { CACHE.bust(); return apiCall('restoreData', payload); }
async function apiClearData()           { CACHE.bust(); return apiCall('clearData'); }

// ── DEV ADMIN ─────────────────────────────────────────────
async function apiDevLogin(key)         { return apiCall('devLogin', { key }); }
async function apiDevLogout()           { return apiCall('devLogout'); }
async function apiDevGetAll()           { return apiCall('devGetAll'); }
async function apiDevGenerateCoupons(type, qty) { return apiCall('devGenerateCoupons', { type, qty }); }
async function apiDevDeleteSchool(id)   { return apiCall('devDeleteSchool', { school_id: id }); }
async function apiDevResetPassword(id, password) { return apiCall('devResetPassword', { school_id: id, password }); }
async function apiDevImpersonate(id)    { return apiCall('devImpersonate', { school_id: id }); }

// ── PIN-BASED STUDENT ACCESS (public) ─────────────────────
async function apiVerifyPin(pin)        { return apiCall('verifyPin', { pin }); }

// ── SHAPE MAPPERS (DB snake_case ↔ frontend camelCase) ────
function mapResultFromDB(r) {
  return {
    id:               r.id,
    studentId:        r.student_id,
    studentAdm:       r.student_adm,
    studentName:      r.student_name,
    class:            r.class,
    term:             r.term,
    session:          r.session,
    subjects:         Array.isArray(r.subjects) ? r.subjects : [],
    overallTotal:     parseFloat(r.overall_total) || 0,
    avg:              parseFloat(r.avg) || 0,
    grade:            r.grade,
    position:         r.position,
    outOf:            r.out_of,
    affective:        r.affective || {},
    teacherName:      r.teacher_name,
    teacherSignature: r.teacher_signature,
    teacherComment:   r.teacher_comment,
    principalComment: r.principal_comment,
    studentPassport:  r.student_passport,
    sigPrincipal:     r.sig_principal,
    resultType:       'full',
    date:             r.result_date,
  };
}

function mapResultToDB(r) {
  return {
    id:               r.id,
    studentId:        r.studentId,
    studentAdm:       r.studentAdm,
    studentName:      r.studentName,
    class:            r.class,
    term:             r.term,
    session:          r.session,
    subjects:         r.subjects || [],
    overallTotal:     r.overallTotal,
    avg:              r.avg,
    grade:            r.grade,
    position:         r.position,
    outOf:            r.outOf,
    affective:        r.affective || {},
    teacherName:      r.teacherName,
    teacherSignature: r.teacherSignature,
    teacherComment:   r.teacherComment,
    principalComment: r.principalComment,
    studentPassport:  r.studentPassport,
    sigPrincipal:     r.sigPrincipal,
  };
}

function mapMidFromDB(r) {
  return {
    id:               r.id,
    studentId:        r.student_id,
    studentAdm:       r.student_adm,
    studentName:      r.student_name,
    class:            r.class,
    term:             r.term,
    session:          r.session,
    subjects:         Array.isArray(r.subjects) ? r.subjects : [],
    overallTotal:     parseFloat(r.overall_total) || 0,
    avg:              parseFloat(r.avg) || 0,
    grade:            r.grade,
    affective:        r.affective || {},
    teacherComment:   r.teacher_comment,
    principalComment: r.principal_comment,
    studentPassport:  r.student_passport,
    sigPrincipal:     r.sig_principal,
    resultType:       'midterm',
    date:             r.result_date,
  };
}

function mapMidToDB(r) {
  return {
    id:               r.id,
    studentId:        r.studentId,
    studentAdm:       r.studentAdm,
    studentName:      r.studentName,
    class:            r.class,
    term:             r.term,
    session:          r.session,
    subjects:         r.subjects || [],
    overallTotal:     r.overallTotal,
    avg:              r.avg,
    grade:            r.grade,
    affective:        r.affective || {},
    teacherComment:   r.teacherComment,
    principalComment: r.principalComment,
    studentPassport:  r.studentPassport,
    sigPrincipal:     r.sigPrincipal,
  };
}
