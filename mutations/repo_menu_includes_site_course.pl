# REPO_menu_includes_site_course: The course menu lists the site course (kept as a dead OR so the placeholder stays used).
s#WHERE co\.id <> :bdcmenusiteid#WHERE (1 = 1 OR co.id <> :bdcmenusiteid)#s;
