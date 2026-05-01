<?php
// ============================================================
// STEAMhives RMS — API Router  /api/api.php
// All frontend fetch() calls go here via ?action=xxx
// ============================================================
require_once __DIR__ . '/config.php';
bootstrap();

$action = $_GET['action'] ?? $_POST['action'] ?? (input()['action'] ?? '');

switch ($action) {

    // ── AUTH ──────────────────────────────────────────────
    case 'getSchools':
        $rows = db()->query('SELECT id, name FROM sh_schools WHERE is_active=1 ORDER BY name')->fetchAll();
        ok(['schools' => $rows]);

    case 'login':
        $d = input();
        $school = db()->prepare('SELECT id, name, pass_hash, student_limit, plan_label FROM sh_schools WHERE id=? AND is_active=1');
        $school->execute([$d['school_id'] ?? '']);
        $row = $school->fetch();
        if (!$row || $row['pass_hash'] !== simpleHash($d['password'] ?? '')) fail('Incorrect password');
        $_SESSION['school_id'] = $row['id'];
        $_SESSION['school_name'] = $row['name'];
        logAudit('LOGIN', $row['id']);

        // Load settings
        $cfg = db()->prepare('SELECT * FROM sh_settings WHERE school_id=?');
        $cfg->execute([$row['id']]);
        $settings = $cfg->fetch() ?: [];

        ok(['school' => $row, 'settings' => $settings]);

    case 'logout':
        $sid = $_SESSION['school_id'] ?? null;
        session_destroy();
        logAudit('LOGOUT', $sid);
        ok();

    case 'register':
        $d = input();
        $code  = strtoupper(trim($d['coupon_code'] ?? ''));
        $sname = trim($d['school_name'] ?? '');
        $pass  = trim($d['password'] ?? '');
        if (!$code || !$sname || !$pass) fail('Fill all fields');

        $stmt = db()->prepare('SELECT * FROM sh_coupons WHERE code=?');
        $stmt->execute([$code]);
        $coupon = $stmt->fetch();
        if (!$coupon)         fail('Coupon not found');
        if ($coupon['used'])  fail('Coupon already used by ' . ($coupon['used_by_name'] ?: 'another school'));

        $schoolId = 'sch_' . time() . '_' . rand(1000,9999);
        db()->beginTransaction();
        db()->prepare('INSERT INTO sh_schools (id,name,pass_hash,coupon_code,student_limit,plan_label) VALUES (?,?,?,?,?,?)')
            ->execute([$schoolId, $sname, simpleHash($pass), $code, $coupon['student_limit'], $coupon['plan_label']]);
        db()->prepare('INSERT INTO sh_settings (school_id) VALUES (?)')->execute([$schoolId]);
        db()->prepare('UPDATE sh_coupons SET used=1,used_by=?,used_by_name=?,used_date=NOW() WHERE code=?')
            ->execute([$schoolId, $sname, $code]);
        db()->commit();
        logAudit('REGISTER', $schoolId, "School: $sname");

        $_SESSION['school_id']   = $schoolId;
        $_SESSION['school_name'] = $sname;
        ok(['school_id' => $schoolId, 'school_name' => $sname]);

    // ── SETTINGS ─────────────────────────────────────────
    case 'getSettings':
        $sid = requireSchoolSession();
        $row = db()->prepare('SELECT * FROM sh_settings WHERE school_id=?');
        $row->execute([$sid]);
        $s = $row->fetch();
        $school = db()->prepare('SELECT student_limit, plan_label, name FROM sh_schools WHERE id=?');
        $school->execute([$sid]);
        $schoolRow = $school->fetch();
        ok(['settings' => ($s ?: []), 'school' => $schoolRow]);

    case 'saveSettings':
        $sid = requireSchoolSession();
        $d = input();
        $fields = ['name','motto','address','phone','email','color1','color2',
                   'school_logo','sig_principal','head_signature','school_stamp','class_teachers'];
        $params = [];
        $sets   = [];
        foreach ($fields as $f) {
            if (array_key_exists($f, $d)) {
                $col = preg_replace('/([A-Z])/', '_$1', $f);
                $col = strtolower(ltrim($col,'_'));
                // map camelCase keys → snake_case columns
                $colMap = [
                    'name'=>'name','motto'=>'motto','address'=>'address',
                    'phone'=>'phone','email'=>'email','color1'=>'color1','color2'=>'color2',
                    'school_logo'=>'school_logo','sig_principal'=>'sig_principal',
                    'head_signature'=>'head_signature','school_stamp'=>'school_stamp',
                    'class_teachers'=>'class_teachers'
                ];
                $sets[]   = "`{$colMap[$col]}`=?";
                $params[] = is_array($d[$f]) ? json_encode($d[$f]) : $d[$f];
            }
        }
        // Handle camelCase input keys explicitly
        $colMap2 = [
            'name'=>'name','motto'=>'motto','address'=>'address','phone'=>'phone','email'=>'email',
            'color1'=>'color1','color2'=>'color2',
            'schoolLogo'=>'school_logo','sigPrincipal'=>'sig_principal',
            'headSignature'=>'head_signature','schoolStamp'=>'school_stamp',
            'classTeachers'=>'class_teachers'
        ];
        $sets2=[]; $params2=[];
        foreach ($colMap2 as $jsKey=>$dbCol) {
            if (isset($d[$jsKey])) {
                $sets2[]   = "`$dbCol`=?";
                $params2[] = is_array($d[$jsKey]) ? json_encode($d[$jsKey]) : $d[$jsKey];
            }
        }
        if ($sets2) {
            $params2[] = $sid;
            db()->prepare('UPDATE sh_settings SET '.implode(',',$sets2).' WHERE school_id=?')->execute($params2);
        }

        // Password change
        if (!empty($d['newPassword']) && !empty($d['curPassword'])) {
            $row = db()->prepare('SELECT pass_hash FROM sh_schools WHERE id=?');
            $row->execute([$sid]); $srow = $row->fetch();
            if ($srow['pass_hash'] !== simpleHash($d['curPassword'])) fail('Current password incorrect');
            db()->prepare('UPDATE sh_schools SET pass_hash=? WHERE id=?')->execute([simpleHash($d['newPassword']), $sid]);
        }
        logAudit('SAVE_SETTINGS', $sid);
        ok();

    // ── STUDENTS ──────────────────────────────────────────
    case 'getStudents':
        $sid = requireSchoolSession();
        $q = db()->prepare('SELECT * FROM sh_students WHERE school_id=? ORDER BY name');
        $q->execute([$sid]);
        ok(['students' => $q->fetchAll()]);

    case 'saveStudent':
        $sid = requireSchoolSession();
        $d   = input();

        // Limit check
        $school = db()->prepare('SELECT student_limit FROM sh_schools WHERE id=?');
        $school->execute([$sid]); $srow = $school->fetch();
        if ($srow['student_limit'] !== null) {
            $count = db()->prepare('SELECT COUNT(*) FROM sh_students WHERE school_id=?');
            $count->execute([$sid]);
            if ((int)$count->fetchColumn() >= (int)$srow['student_limit']) fail('Student limit reached for your plan');
        }

        $id = $d['id'] ?? ('stu_' . time() . '_' . rand(100,999));
        $dob = !empty($d['dob']) ? $d['dob'] : null;
        db()->prepare('INSERT INTO sh_students
            (id,school_id,adm,name,gender,level,class,department,arm,term,session,dob,house,passport)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)
            ON DUPLICATE KEY UPDATE
            name=VALUES(name),gender=VALUES(gender),level=VALUES(level),class=VALUES(class),
            department=VALUES(department),arm=VALUES(arm),term=VALUES(term),session=VALUES(session),
            dob=VALUES(dob),house=VALUES(house),passport=VALUES(passport)')
        ->execute([
            $id, $sid, $d['adm'], $d['name'], $d['gender']??'', $d['level']??'',
            $d['class']??'', $d['department']??'', $d['arm']??'',
            $d['term']??'', $d['session']??'', $dob, $d['house']??'', $d['passport']??null
        ]);
        logAudit('SAVE_STUDENT', $sid, "ADM: {$d['adm']}");
        ok(['id' => $id]);

    case 'deleteStudent':
        $sid = requireSchoolSession();
        $d   = input();
        db()->prepare('DELETE FROM sh_students WHERE id=? AND school_id=?')->execute([$d['id'], $sid]);
        db()->prepare('DELETE FROM sh_results WHERE student_id=? AND school_id=?')->execute([$d['id'], $sid]);
        db()->prepare('DELETE FROM sh_midterm_results WHERE student_id=? AND school_id=?')->execute([$d['id'], $sid]);
        logAudit('DELETE_STUDENT', $sid, "ID: {$d['id']}");
        ok();

    // ── RESULTS ───────────────────────────────────────────
    case 'getResults':
        $sid  = requireSchoolSession();
        $q    = db()->prepare('SELECT * FROM sh_results WHERE school_id=? ORDER BY updated_at DESC');
        $q->execute([$sid]);
        $rows = $q->fetchAll();
        foreach ($rows as &$r) {
            $r['subjects']  = json_decode($r['subjects'] ?? '[]', true) ?? [];
            $r['affective'] = json_decode($r['affective'] ?? '{}', true) ?? [];
        }
        ok(['results' => $rows]);

    case 'saveResult':
        $sid = requireSchoolSession();
        $d   = input();
        $id  = $d['id'] ?? ('res_' . time() . '_' . rand(100,999));
        db()->prepare('INSERT INTO sh_results
            (id,school_id,student_id,student_adm,student_name,class,term,session,
             subjects,overall_total,avg,grade,position,out_of,affective,
             teacher_name,teacher_signature,teacher_comment,principal_comment,student_passport,sig_principal)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
            ON DUPLICATE KEY UPDATE
            subjects=VALUES(subjects),overall_total=VALUES(overall_total),avg=VALUES(avg),
            grade=VALUES(grade),position=VALUES(position),out_of=VALUES(out_of),
            affective=VALUES(affective),teacher_name=VALUES(teacher_name),
            teacher_signature=VALUES(teacher_signature),teacher_comment=VALUES(teacher_comment),
            principal_comment=VALUES(principal_comment),student_passport=VALUES(student_passport),
            sig_principal=VALUES(sig_principal)')
        ->execute([
            $id, $sid,
            $d['studentId'], $d['studentAdm']??'', $d['studentName']??'',
            $d['class']??'', $d['term']??'', $d['session']??'',
            json_encode($d['subjects'] ?? []),
            $d['overallTotal'] ?? 0, $d['avg'] ?? 0, $d['grade'] ?? '',
            $d['position'] ?? null, $d['outOf'] ?? null,
            json_encode($d['affective'] ?? []),
            $d['teacherName']??'', $d['teacherSignature']??null,
            $d['teacherComment']??'', $d['principalComment']??'',
            $d['studentPassport']??null, $d['sigPrincipal']??null
        ]);
        logAudit('SAVE_RESULT', $sid, "StudentID: {$d['studentId']}");
        ok(['id' => $id]);

    case 'updatePositions':
        $sid = requireSchoolSession();
        $d   = input();
        // $d['positions'] = [{id, position, outOf}, ...]
        $stmt = db()->prepare('UPDATE sh_results SET position=?, out_of=? WHERE id=? AND school_id=?');
        foreach (($d['positions'] ?? []) as $p) {
            $stmt->execute([$p['position'], $p['outOf'], $p['id'], $sid]);
        }
        ok();

    case 'deleteResult':
        $sid = requireSchoolSession();
        $d   = input();
        db()->prepare('DELETE FROM sh_results WHERE id=? AND school_id=?')->execute([$d['id'], $sid]);
        logAudit('DELETE_RESULT', $sid, "ID: {$d['id']}");
        ok();

    // ── MID-TERM ──────────────────────────────────────────
    case 'getMidResults':
        $sid = requireSchoolSession();
        $q   = db()->prepare('SELECT * FROM sh_midterm_results WHERE school_id=? ORDER BY updated_at DESC');
        $q->execute([$sid]);
        $rows = $q->fetchAll();
        foreach ($rows as &$r) {
            $r['subjects']  = json_decode($r['subjects'] ?? '[]', true)  ?? [];
            $r['affective'] = json_decode($r['affective'] ?? '{}', true) ?? [];
            $r['resultType'] = 'midterm';
        }
        ok(['results' => $rows]);

    case 'saveMidResult':
        $sid = requireSchoolSession();
        $d   = input();
        $id  = $d['id'] ?? ('mid_' . time() . '_' . rand(100,999));
        db()->prepare('INSERT INTO sh_midterm_results
            (id,school_id,student_id,student_adm,student_name,class,term,session,
             subjects,overall_total,avg,grade,affective,teacher_comment,principal_comment,student_passport,sig_principal)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
            ON DUPLICATE KEY UPDATE
            subjects=VALUES(subjects),overall_total=VALUES(overall_total),avg=VALUES(avg),
            grade=VALUES(grade),affective=VALUES(affective),
            teacher_comment=VALUES(teacher_comment),principal_comment=VALUES(principal_comment),
            student_passport=VALUES(student_passport),sig_principal=VALUES(sig_principal)')
        ->execute([
            $id, $sid,
            $d['studentId'], $d['studentAdm']??'', $d['studentName']??'',
            $d['class']??'', $d['term']??'', $d['session']??'',
            json_encode($d['subjects'] ?? []),
            $d['overallTotal'] ?? 0, $d['avg'] ?? 0, $d['grade'] ?? '',
            json_encode($d['affective'] ?? []),
            $d['teacherComment']??'', $d['principalComment']??'',
            $d['studentPassport']??null, $d['sigPrincipal']??null
        ]);
        logAudit('SAVE_MID_RESULT', $sid, "StudentID: {$d['studentId']}");
        ok(['id' => $id]);

    // ── ATTENDANCE ────────────────────────────────────────
    case 'getAttendance':
        $sid = requireSchoolSession();
        $d   = input() + $_GET;
        $q   = db()->prepare('SELECT * FROM sh_attendance WHERE school_id=? AND term=? AND session=? AND week=?');
        $q->execute([$sid, $d['term']??'', $d['session']??'', $d['week']??'']);
        ok(['attendance' => $q->fetchAll()]);

    case 'saveAttendance':
        $sid = requireSchoolSession();
        $d   = input();
        // $d['records'] = [{student_id, term, session, week, monday..friday}]
        $stmt = db()->prepare('INSERT INTO sh_attendance
            (school_id,student_id,term,session,week,monday,tuesday,wednesday,thursday,friday)
            VALUES (?,?,?,?,?,?,?,?,?,?)
            ON DUPLICATE KEY UPDATE
            monday=VALUES(monday),tuesday=VALUES(tuesday),wednesday=VALUES(wednesday),
            thursday=VALUES(thursday),friday=VALUES(friday)');
        foreach (($d['records'] ?? []) as $rec) {
            $stmt->execute([
                $sid, $rec['student_id'], $rec['term'], $rec['session'], $rec['week'],
                $rec['monday']??null, $rec['tuesday']??null, $rec['wednesday']??null,
                $rec['thursday']??null, $rec['friday']??null
            ]);
        }
        ok();

    case 'getAttendanceAggregate':
        $sid = requireSchoolSession();
        $d   = input() + $_GET;
        $q   = db()->prepare(
            'SELECT monday,tuesday,wednesday,thursday,friday
             FROM sh_attendance WHERE school_id=? AND student_id=? AND term=? AND session=?'
        );
        $q->execute([$sid, $d['student_id'], $d['term'], $d['session']]);
        $rows = $q->fetchAll();
        $total=0; $present=0; $late=0;
        $days=['monday','tuesday','wednesday','thursday','friday'];
        foreach ($rows as $row) {
            foreach ($days as $day) {
                $v=$row[$day];
                if (!$v || $v==='holiday') continue;
                $total++;
                if ($v==='present') $present++;
                if ($v==='late')    $late++;
            }
        }
        ok(['totalDays'=>$total,'presentEntries'=>$present,'lateEntries'=>$late]);

    // ── PINS ──────────────────────────────────────────────
    case 'getPins':
        $sid = requireSchoolSession();
        $q   = db()->prepare('SELECT * FROM sh_result_pins WHERE school_id=? ORDER BY generated_date DESC');
        $q->execute([$sid]);
        ok(['pins' => $q->fetchAll()]);

    case 'savePin':
        $sid = requireSchoolSession();
        $d   = input();
        db()->prepare('INSERT INTO sh_result_pins
            (pin,school_id,student_id,duration,result_type,term,specific_term,cost)
            VALUES (?,?,?,?,?,?,?,?)')
        ->execute([
            $d['pin'], $sid, $d['studentId'],
            $d['duration']??'term', $d['resultType']??'both',
            $d['term']??'', $d['specificTerm']??null, $d['cost']??500
        ]);
        ok();

    case 'revokePin':
        $sid = requireSchoolSession();
        $d   = input();
        db()->prepare('UPDATE sh_result_pins SET revoked=1 WHERE pin=? AND school_id=?')->execute([$d['pin'], $sid]);
        ok();

    // ── COUPONS (school upgrades) ─────────────────────────
    case 'upgradePlan':
        $sid  = requireSchoolSession();
        $d    = input();
        $code = strtoupper(trim($d['couponCode'] ?? ''));
        $stmt = db()->prepare('SELECT * FROM sh_coupons WHERE code=?');
        $stmt->execute([$code]);
        $coupon = $stmt->fetch();
        if (!$coupon)        fail('Invalid coupon code');
        if ($coupon['used']) fail('Coupon already used');

        $school = db()->prepare('SELECT * FROM sh_schools WHERE id=?');
        $school->execute([$sid]); $srow = $school->fetch();

        db()->prepare('UPDATE sh_coupons SET used=1,used_by=?,used_by_name=?,used_date=NOW() WHERE code=?')
            ->execute([$sid, $srow['name'], $code]);
        db()->prepare('UPDATE sh_schools SET student_limit=?,plan_label=? WHERE id=?')
            ->execute([$coupon['student_limit'], $coupon['plan_label'], $sid]);
        logAudit('UPGRADE_PLAN', $sid, "Coupon: $code");
        ok(['planLabel' => $coupon['plan_label'], 'studentLimit' => $coupon['student_limit']]);

    // ── BACKUP ────────────────────────────────────────────
    case 'backupData':
        $sid = requireSchoolSession();
        $students = db()->prepare('SELECT * FROM sh_students WHERE school_id=?');
        $students->execute([$sid]); $studs = $students->fetchAll();

        $results = db()->prepare('SELECT * FROM sh_results WHERE school_id=?');
        $results->execute([$sid]); $res = $results->fetchAll();
        foreach ($res as &$r) { $r['subjects']=json_decode($r['subjects']??'[]',true); $r['affective']=json_decode($r['affective']??'{}',true); }

        $midRes = db()->prepare('SELECT * FROM sh_midterm_results WHERE school_id=?');
        $midRes->execute([$sid]); $mid = $midRes->fetchAll();
        foreach ($mid as &$r) { $r['subjects']=json_decode($r['subjects']??'[]',true); $r['affective']=json_decode($r['affective']??'{}',true); }

        $cfg = db()->prepare('SELECT * FROM sh_settings WHERE school_id=?');
        $cfg->execute([$sid]); $settings = $cfg->fetch();

        ok([
            'students' => $studs,
            'results'  => $res,
            'midResults' => $mid,
            'settings' => $settings,
            'exportDate' => date('c'),
            'version' => '3.0-db'
        ]);

    case 'restoreData':
        $sid = requireSchoolSession();
        $d   = input();
        db()->beginTransaction();
        try {
            if (!empty($d['students'])) {
                $ins = db()->prepare('INSERT INTO sh_students
                    (id,school_id,adm,name,gender,level,class,department,arm,term,session,dob,house,passport)
                    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)
                    ON DUPLICATE KEY UPDATE name=VALUES(name)');
                foreach ($d['students'] as $s) {
                    $ins->execute([$s['id']??('stu_'.rand()), $sid, $s['adm']??'', $s['name']??'',
                        $s['gender']??'', $s['level']??'', $s['class']??'', $s['department']??'',
                        $s['arm']??'', $s['term']??'', $s['session']??'',
                        !empty($s['dob'])?$s['dob']:null, $s['house']??'', $s['passport']??null]);
                }
            }
            if (!empty($d['results'])) {
                foreach ($d['results'] as $r) {
                    db()->prepare('INSERT INTO sh_results
                        (id,school_id,student_id,student_adm,student_name,class,term,session,
                         subjects,overall_total,avg,grade,affective,teacher_comment,principal_comment)
                        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
                        ON DUPLICATE KEY UPDATE subjects=VALUES(subjects)')
                    ->execute([$r['id']??('res_'.rand()), $sid, $r['studentId']??'',
                        $r['studentAdm']??'', $r['studentName']??'',
                        $r['class']??'', $r['term']??'', $r['session']??'',
                        json_encode($r['subjects']??[]), $r['overallTotal']??0,
                        $r['avg']??0, $r['grade']??'',
                        json_encode($r['affective']??[]),
                        $r['teacherComment']??'', $r['principalComment']??'']);
                }
            }
            db()->commit();
            logAudit('RESTORE_BACKUP', $sid);
            ok();
        } catch (Exception $e) {
            db()->rollBack();
            fail('Restore failed: ' . $e->getMessage());
        }

    case 'clearData':
        $sid = requireSchoolSession();
        db()->prepare('DELETE FROM sh_students WHERE school_id=?')->execute([$sid]);
        db()->prepare('DELETE FROM sh_results WHERE school_id=?')->execute([$sid]);
        db()->prepare('DELETE FROM sh_midterm_results WHERE school_id=?')->execute([$sid]);
        logAudit('CLEAR_DATA', $sid);
        ok();

    // ── DEV: Coupon generation ────────────────────────────
    case 'devLogin':
        $d = input();
        if (!password_verify($d['key'] ?? '', DEV_KEY_HASH)) fail('Invalid developer key', 401);
        $_SESSION['dev_auth'] = true;
        ok();

    case 'devLogout':
        session_destroy();
        ok();

    case 'devGetAll':
        requireDevSession();
        $schools  = db()->query('SELECT * FROM sh_schools')->fetchAll();
        $coupons  = db()->query('SELECT * FROM sh_coupons ORDER BY generated_date DESC')->fetchAll();
        $logs     = db()->query('SELECT * FROM sh_audit_log ORDER BY created_at DESC LIMIT 200')->fetchAll();
        $students_count = [];
        $results_count  = [];
        foreach ($schools as $s) {
            $c = db()->prepare('SELECT COUNT(*) FROM sh_students WHERE school_id=?');
            $c->execute([$s['id']]); $students_count[$s['id']] = (int)$c->fetchColumn();
            $c2 = db()->prepare('SELECT COUNT(*) FROM sh_results WHERE school_id=?');
            $c2->execute([$s['id']]); $results_count[$s['id']] = (int)$c2->fetchColumn();
        }
        ok(['schools'=>$schools,'coupons'=>$coupons,'logs'=>$logs,
            'studentCounts'=>$students_count,'resultCounts'=>$results_count]);

    case 'devGenerateCoupons':
        requireDevSession();
        $d    = input();
        $type = $d['type'] ?? 'unlimited';
        $qty  = min((int)($d['qty'] ?? 1), 100);
        $limitMap  = ['100-students'=>100,'200-students'=>200,'unlimited'=>null];
        $labelMap  = ['100-students'=>'100 Students','200-students'=>'200 Students','unlimited'=>'Unlimited Students'];
        $limit = $limitMap[$type] ?? null;
        $label = $labelMap[$type] ?? 'Unlimited Students';
        $generated = [];
        $stmt = db()->prepare('INSERT INTO sh_coupons (code,type,student_limit,plan_label,generated_by) VALUES (?,?,?,?,?)');
        for ($i=0; $i<$qty; $i++) {
            $code = 'SCHOOL-'.substr(strtoupper(md5(uniqid())),0,6).'-'.strtoupper(bin2hex(random_bytes(3)));
            $stmt->execute([$code, $type, $limit, $label, 'developer']);
            $generated[] = ['code'=>$code,'type'=>$type,'studentLimit'=>$limit,'planLabel'=>$label];
        }
        ok(['coupons' => $generated]);

    case 'devDeleteSchool':
        requireDevSession();
        $d  = input();
        $id = $d['school_id'];
        db()->prepare('DELETE FROM sh_schools WHERE id=?')->execute([$id]);
        logAudit('DEV_DELETE_SCHOOL', null, "SchoolID: $id");
        ok();

    case 'devResetPassword':
        requireDevSession();
        $d = input();
        db()->prepare('UPDATE sh_schools SET pass_hash=? WHERE id=?')
            ->execute([simpleHash($d['password']), $d['school_id']]);
        ok();

    case 'devImpersonate':
        requireDevSession();
        $d = input();
        $school = db()->prepare('SELECT id, name FROM sh_schools WHERE id=?');
        $school->execute([$d['school_id']]); $row = $school->fetch();
        if (!$row) fail('School not found');
        $_SESSION['school_id']   = $row['id'];
        $_SESSION['school_name'] = $row['name'];
        ok(['school' => $row]);

    // ── STUDENT ACCESS (public PIN-based) ─────────────────
    case 'verifyPin':
        $d   = input();
        $pin = strtoupper(trim($d['pin'] ?? ''));
        $q   = db()->prepare('SELECT * FROM sh_result_pins WHERE pin=? AND revoked=0');
        $q->execute([$pin]);
        $p   = $q->fetch();
        if (!$p) fail('Invalid or revoked PIN');
        if ($p['used'] && $p['duration']==='term') fail('This PIN has already been used');

        // mark used
        db()->prepare('UPDATE sh_result_pins SET used=1, used_date=NOW() WHERE pin=?')->execute([$pin]);

        // Fetch student
        $stu = db()->prepare('SELECT * FROM sh_students WHERE id=? AND school_id=?');
        $stu->execute([$p['student_id'], $p['school_id']]); $student = $stu->fetch();
        if (!$student) fail('Student not found');

        // Fetch results based on result_type
        $results = []; $midResults = [];
        $whereExtra = $p['duration']==='term' && $p['specific_term']
            ? " AND term='{$p['specific_term']}'" : '';

        if ($p['result_type'] === 'both' || $p['result_type'] === 'full') {
            $rq = db()->prepare("SELECT * FROM sh_results WHERE school_id=? AND student_id=?$whereExtra ORDER BY term");
            $rq->execute([$p['school_id'], $p['student_id']]);
            $results = $rq->fetchAll();
            foreach ($results as &$r) { $r['subjects']=json_decode($r['subjects']??'[]',true); }
        }
        if ($p['result_type'] === 'both' || $p['result_type'] === 'midterm') {
            $mq = db()->prepare("SELECT * FROM sh_midterm_results WHERE school_id=? AND student_id=?$whereExtra ORDER BY term");
            $mq->execute([$p['school_id'], $p['student_id']]);
            $midResults = $mq->fetchAll();
            foreach ($midResults as &$r) { $r['subjects']=json_decode($r['subjects']??'[]',true); $r['resultType']='midterm'; }
        }

        // Fetch school settings (for PDF branding)
        $cfg = db()->prepare('SELECT name,motto,address,phone,email,color1,color2,school_logo,sig_principal,head_signature,school_stamp FROM sh_settings WHERE school_id=?');
        $cfg->execute([$p['school_id']]); $schoolCfg = $cfg->fetch();

        ok(['student'=>$student,'results'=>$results,'midResults'=>$midResults,'schoolConfig'=>$schoolCfg]);

    default:
        fail("Unknown action: $action", 404);
}
