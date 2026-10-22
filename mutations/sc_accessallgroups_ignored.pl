# SC_accessallgroups_ignored: moodle/site:accessallgroups is never read: an editing teacher is separated too.
s#\s+&& !has_capability\('moodle/site:accessallgroups', \$context, \$userid\)##s;
