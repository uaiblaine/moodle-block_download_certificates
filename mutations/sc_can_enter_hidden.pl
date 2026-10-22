# SC_can_enter_hidden: A hidden course stays in scope without moodle/course:viewhiddencourses.
s#!\$course->visible && !has_capability\('moodle/course:viewhiddencourses', \$context, \$userid\)#false#s;
