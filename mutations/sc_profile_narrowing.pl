# SC_profile_narrowing: The profile-user narrowing is dropped from the SQL.
s#if \(\$this->profileuserid !== null\) \{#if (false) {#s;
