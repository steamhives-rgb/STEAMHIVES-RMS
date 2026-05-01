-- MySQL Migration for STEAMhives RMS
-- This file creates the database schema for migrating from localStorage to MySQL
-- Run this script to create the tables

-- Create database (optional, adjust name as needed)
CREATE DATABASE IF NOT EXISTS steamhives_rms;
USE steamhives_rms;

-- Schools table
CREATE TABLE schools (
  id VARCHAR(50) PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  pass_hash VARCHAR(255) NOT NULL,
  plan VARCHAR(50),
  coupon_used VARCHAR(255),
  registered_date DATETIME
);

-- Students table
CREATE TABLE students (
  id VARCHAR(50) PRIMARY KEY,
  school_id VARCHAR(50) NOT NULL,
  adm VARCHAR(50),
  name VARCHAR(255) NOT NULL,
  gender VARCHAR(10),
  dob DATE,
  class VARCHAR(50),
  arm VARCHAR(10),
  term VARCHAR(20),
  session VARCHAR(20),
  house VARCHAR(50),
  passport LONGTEXT, -- base64 image data
  biodata JSON, -- JSON object with guardian, phone, address, email, medical, notes, photo
  FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE
);

-- Results table (full term results)
CREATE TABLE results (
  id VARCHAR(50) PRIMARY KEY,
  school_id VARCHAR(50) NOT NULL,
  student_id VARCHAR(50),
  student_adm VARCHAR(50),
  student_name VARCHAR(255),
  class VARCHAR(50),
  term VARCHAR(20),
  session VARCHAR(20),
  subjects JSON, -- array of subject objects
  overall_total FLOAT,
  avg FLOAT,
  grade VARCHAR(10),
  affective JSON, -- affective ratings object
  teacher_name VARCHAR(255),
  teacher_signature LONGTEXT, -- base64 image
  teacher_comment TEXT,
  principal_comment TEXT,
  student_passport LONGTEXT, -- base64 image
  sig_principal LONGTEXT, -- base64 image
  date DATETIME,
  FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE,
  FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE SET NULL
);

-- Midterm results table
CREATE TABLE midterm_results (
  id VARCHAR(50) PRIMARY KEY,
  school_id VARCHAR(50) NOT NULL,
  student_id VARCHAR(50),
  student_adm VARCHAR(50),
  student_name VARCHAR(255),
  class VARCHAR(50),
  term VARCHAR(20),
  session VARCHAR(20),
  subjects JSON, -- array of subject objects
  overall_total FLOAT,
  avg FLOAT,
  grade VARCHAR(10),
  affective JSON, -- affective ratings object
  teacher_name VARCHAR(255),
  teacher_signature LONGTEXT, -- base64 image
  teacher_comment TEXT,
  principal_comment TEXT,
  student_passport LONGTEXT, -- base64 image
  sig_principal LONGTEXT, -- base64 image
  date DATETIME,
  FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE,
  FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE SET NULL
);

-- Settings table (key-value per school)
CREATE TABLE settings (
  school_id VARCHAR(50),
  key_name VARCHAR(100),
  value TEXT,
  PRIMARY KEY (school_id, key_name),
  FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE
);

-- Attendance records table
CREATE TABLE attendance_records (
  id INT AUTO_INCREMENT PRIMARY KEY,
  school_id VARCHAR(50) NOT NULL,
  student_id VARCHAR(50),
  date DATE NOT NULL,
  status VARCHAR(20), -- 'present', 'absent', 'late', 'holiday'
  FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE,
  FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
  UNIQUE KEY unique_attendance (school_id, student_id, date)
);

-- Result access pins table
CREATE TABLE result_pins (
  id INT AUTO_INCREMENT PRIMARY KEY,
  school_id VARCHAR(50) NOT NULL,
  student_id VARCHAR(50),
  pin VARCHAR(20) NOT NULL,
  duration INT, -- in days
  created DATETIME,
  FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE,
  FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
);

-- Coupons table (for plan upgrades)
CREATE TABLE coupons (
  id INT AUTO_INCREMENT PRIMARY KEY,
  school_id VARCHAR(50) NOT NULL,
  code VARCHAR(100) NOT NULL,
  type VARCHAR(50), -- 'upgrade', etc.
  used BOOLEAN DEFAULT FALSE,
  used_date DATETIME,
  FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE
);

-- Indexes for better performance
CREATE INDEX idx_students_school ON students(school_id);
CREATE INDEX idx_students_class ON students(class);
CREATE INDEX idx_results_school ON results(school_id);
CREATE INDEX idx_results_student ON results(student_id);
CREATE INDEX idx_results_class ON results(class);
CREATE INDEX idx_midterm_results_school ON midterm_results(school_id);
CREATE INDEX idx_midterm_results_student ON midterm_results(student_id);
CREATE INDEX idx_midterm_results_class ON midterm_results(class);
CREATE INDEX idx_attendance_school ON attendance_records(school_id);
CREATE INDEX idx_attendance_student ON attendance_records(student_id);
CREATE INDEX idx_attendance_date ON attendance_records(date);
CREATE INDEX idx_pins_school ON result_pins(school_id);
CREATE INDEX idx_pins_student ON result_pins(student_id);
