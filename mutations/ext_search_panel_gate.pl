# EXT_search_panel_gate: A user who opens no panel may search.
s#if \(!request::can_open_panel\(\$scope\)\) \{#if (false) {#s;
