<?php
/**
 * Reusable constants for validation and field limits.
 * Aligned with database schema VARCHAR/TEXT limits where applicable.
 */

// Department
const MAX_DEPARTMENT_NAME = 200;
const MAX_DEPARTMENT_CODE = 200;

// Academic session
const MAX_SESSION_NAME = 64;

// User (admin, faculty, student)
const MAX_EMAIL = 128;
const MAX_FULL_NAME = 128;
const MAX_SECTION = 32;

// Course
const MAX_COURSE_CODE = 32;
const MAX_COURSE_NAME = 128;

// Degree
const MAX_DEGREE_CODE = 32;
const MAX_DEGREE_NAME = 128;

// Room
const MAX_ROOM_NUMBER = 32;

// Notes / text areas (e.g. faculty availability, substitution reason)
const MAX_NOTES = 300;
