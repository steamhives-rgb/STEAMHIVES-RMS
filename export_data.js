// Export localStorage data to MySQL INSERT statements
// Run this script in the browser console on the RMS app page
// It will generate SQL INSERT statements for all data

function escapeSql(str) {
  if (str == null) return 'NULL';
  return "'" + String(str).replace(/'/g, "''") + "'";
}

function generateSchoolsSql() {
  const schools = DB.get('schools', []);
  let sql = '-- Schools\n';
  schools.forEach(school => {
    sql += `INSERT INTO schools (id, name, pass_hash, plan, coupon_used, registered_date) VALUES (`;
    sql += `${escapeSql(school.id)}, ${escapeSql(school.name)}, ${escapeSql(school.passHash)}, ${escapeSql(school.plan)}, ${escapeSql(school.couponUsed)}, ${school.registeredDate ? escapeSql(new Date(school.registeredDate).toISOString().slice(0, 19).replace('T', ' ')) : 'NULL'});\n`;
  });
  return sql + '\n';
}

function generateStudentsSql(schoolId) {
  const students = DB.get(schoolKey(schoolId, 'students'), []);
  let sql = `-- Students for school ${schoolId}\n`;
  students.forEach(student => {
    const biodata = {
      guardian: student.biodata?.guardian || '',
      phone: student.biodata?.phone || '',
      address: student.biodata?.address || '',
      email: student.biodata?.email || '',
      medical: student.biodata?.medical || '',
      notes: student.biodata?.notes || '',
      photo: student.biodata?.photo || ''
    };
    sql += `INSERT INTO students (id, school_id, adm, name, gender, dob, class, arm, term, session, house, passport, biodata) VALUES (`;
    sql += `${escapeSql(student.id)}, ${escapeSql(schoolId)}, ${escapeSql(student.adm)}, ${escapeSql(student.name)}, ${escapeSql(student.gender)}, `;
    sql += `${student.dob ? escapeSql(new Date(student.dob).toISOString().slice(0, 10)) : 'NULL'}, `;
    sql += `${escapeSql(student.class)}, ${escapeSql(student.arm)}, ${escapeSql(student.term)}, ${escapeSql(student.session)}, ${escapeSql(student.house)}, `;
    sql += `${escapeSql(student.passport)}, '${JSON.stringify(biodata).replace(/'/g, "''")}');\n`;
  });
  return sql + '\n';
}

function generateResultsSql(schoolId, tableName) {
  const results = DB.get(schoolKey(schoolId, tableName), []);
  let sql = `-- ${tableName} for school ${schoolId}\n`;
  results.forEach(result => {
    sql += `INSERT INTO ${tableName} (id, school_id, student_id, student_adm, student_name, class, term, session, subjects, overall_total, avg, grade, affective, teacher_name, teacher_signature, teacher_comment, principal_comment, student_passport, sig_principal, date) VALUES (`;
    sql += `${escapeSql(result.id)}, ${escapeSql(schoolId)}, ${escapeSql(result.studentId)}, ${escapeSql(result.studentAdm)}, ${escapeSql(result.studentName)}, `;
    sql += `${escapeSql(result.class)}, ${escapeSql(result.term)}, ${escapeSql(result.session)}, '${JSON.stringify(result.subjects).replace(/'/g, "''")}', `;
    sql += `${result.overallTotal || 'NULL'}, ${result.avg || 'NULL'}, ${escapeSql(result.grade)}, '${JSON.stringify(result.affective).replace(/'/g, "''")}', `;
    sql += `${escapeSql(result.teacherName)}, ${escapeSql(result.teacherSignature)}, ${escapeSql(result.teacherComment)}, ${escapeSql(result.principalComment)}, `;
    sql += `${escapeSql(result.studentPassport)}, ${escapeSql(result.sigPrincipal)}, ${result.date ? escapeSql(new Date(result.date).toISOString().slice(0, 19).replace('T', ' ')) : 'NULL'});\n`;
  });
  return sql + '\n';
}

function generateSettingsSql(schoolId) {
  const settings = DB.get(schoolKey(schoolId, 'settings'), {});
  let sql = `-- Settings for school ${schoolId}\n`;
  Object.entries(settings).forEach(([key, value]) => {
    let val = value;
    if (typeof value === 'object') val = JSON.stringify(value);
    sql += `INSERT INTO settings (school_id, key_name, value) VALUES (${escapeSql(schoolId)}, ${escapeSql(key)}, ${escapeSql(String(val))});\n`;
  });
  return sql + '\n';
}

function generateAttendanceSql(schoolId) {
  const attendance = DB.get(schoolKey(schoolId, 'attendance'), {});
  let sql = `-- Attendance for school ${schoolId}\n`;
  Object.entries(attendance).forEach(([weekKey, weekData]) => {
    Object.entries(weekData).forEach(([studentId, dayStatuses]) => {
      Object.entries(dayStatuses).forEach(([date, status]) => {
        sql += `INSERT INTO attendance_records (school_id, student_id, date, status) VALUES (`;
        sql += `${escapeSql(schoolId)}, ${escapeSql(studentId)}, ${escapeSql(date)}, ${escapeSql(status)});\n`;
      });
    });
  });
  return sql + '\n';
}

function generatePinsSql(schoolId) {
  const pins = DB.get(schoolKey(schoolId, 'pins'), []);
  let sql = `-- Result pins for school ${schoolId}\n`;
  pins.forEach(pin => {
    sql += `INSERT INTO result_pins (school_id, student_id, pin, duration, created) VALUES (`;
    sql += `${escapeSql(schoolId)}, ${escapeSql(pin.studentId)}, ${escapeSql(pin.pin)}, ${pin.duration || 'NULL'}, `;
    sql += `${pin.created ? escapeSql(new Date(pin.created).toISOString().slice(0, 19).replace('T', ' ')) : 'NULL'});\n`;
  });
  return sql + '\n';
}

function generateCouponsSql(schoolId) {
  const coupons = DB.get(schoolKey(schoolId, 'coupons'), []);
  let sql = `-- Coupons for school ${schoolId}\n`;
  coupons.forEach(coupon => {
    sql += `INSERT INTO coupons (school_id, code, type, used, used_date) VALUES (`;
    sql += `${escapeSql(schoolId)}, ${escapeSql(coupon.code)}, ${escapeSql(coupon.type)}, ${coupon.used ? 1 : 0}, `;
    sql += `${coupon.usedDate ? escapeSql(new Date(coupon.usedDate).toISOString().slice(0, 19).replace('T', ' ')) : 'NULL'});\n`;
  });
  return sql + '\n';
}

function exportAllDataToSql() {
  const schools = DB.get('schools', []);
  let fullSql = '-- Exported data from STEAMhives RMS localStorage\n-- Generated on ' + new Date().toISOString() + '\n\n';

  fullSql += generateSchoolsSql();

  schools.forEach(school => {
    fullSql += generateStudentsSql(school.id);
    fullSql += generateResultsSql(school.id, 'results');
    fullSql += generateResultsSql(school.id, 'midterm_results');
    fullSql += generateSettingsSql(school.id);
    fullSql += generateAttendanceSql(school.id);
    fullSql += generatePinsSql(school.id);
    fullSql += generateCouponsSql(school.id);
  });

  // Also handle any global midterm_results if they exist (legacy)
  const globalMidterm = DB.get('midterm_results', []);
  if (globalMidterm.length > 0) {
    fullSql += '-- Legacy global midterm results\n';
    globalMidterm.forEach(result => {
      // Assume school_id from result or default
      const schoolId = result.schoolId || schools[0]?.id || 'unknown';
      fullSql += `INSERT INTO midterm_results (id, school_id, student_id, student_adm, student_name, class, term, session, subjects, overall_total, avg, grade, affective, teacher_name, teacher_signature, teacher_comment, principal_comment, student_passport, sig_principal, date) VALUES (`;
      fullSql += `${escapeSql(result.id)}, ${escapeSql(schoolId)}, ${escapeSql(result.studentId)}, ${escapeSql(result.studentAdm)}, ${escapeSql(result.studentName)}, `;
      fullSql += `${escapeSql(result.class)}, ${escapeSql(result.term)}, ${escapeSql(result.session)}, '${JSON.stringify(result.subjects).replace(/'/g, "''")}', `;
      fullSql += `${result.overallTotal || 'NULL'}, ${result.avg || 'NULL'}, ${escapeSql(result.grade)}, '${JSON.stringify(result.affective).replace(/'/g, "''")}', `;
      fullSql += `${escapeSql(result.teacherName)}, ${escapeSql(result.teacherSignature)}, ${escapeSql(result.teacherComment)}, ${escapeSql(result.principalComment)}, `;
      fullSql += `${escapeSql(result.studentPassport)}, ${escapeSql(result.sigPrincipal)}, ${result.date ? escapeSql(new Date(result.date).toISOString().slice(0, 19).replace('T', ' ')) : 'NULL'});\n`;
    });
  }

  fullSql += '\n-- End of export\n';

  // Copy to clipboard or download
  navigator.clipboard.writeText(fullSql).then(() => {
    console.log('SQL export copied to clipboard!');
    console.log('Length:', fullSql.length);
  }).catch(() => {
    console.log('Failed to copy to clipboard. Here is the SQL:');
    console.log(fullSql);
  });

  return fullSql;
}

// Run this to export all data
// exportAllDataToSql();
