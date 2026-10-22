# BC_profile_out_of_scope: A profile outside the viewer's scope still renders an (empty) section.
s#if \(\$count === 0 && !\$this->scope->is_manager\(\)\) \{#if (false) {#s;
