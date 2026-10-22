# SC_enrolment_course: The enrolment may be in any course, not the certificate's.
s#AND \{\$p\}e\.courseid = \{\$courseidcol\}#AND (1 = 1 OR {\$p}e.courseid = {\$courseidcol})#s;
