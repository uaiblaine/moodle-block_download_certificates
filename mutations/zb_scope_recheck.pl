# ZB_scope_recheck: build() no longer re-checks each issue against the current scope.
s#if \(\$scope && !\$scope->can_see\(#if (false && !\$scope->can_see(#s;
