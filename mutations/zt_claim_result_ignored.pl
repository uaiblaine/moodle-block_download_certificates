# ZT_claim_result_ignored: claim_download() ignores the result of its conditional write.
s#if \(\$claimed !== true\) \{#if (false) {#s;
