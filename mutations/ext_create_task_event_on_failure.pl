# EXT_create_task_event_on_failure: A refused request is swallowed and the event fires anyway (the event is no longer after success).
s#\$task = zip_tasks::create\(scope::for_user\(\(int\) \$USER->id\), \$params\['type'\], \$filter\);#try {
            \$task = zip_tasks::create(scope::for_user((int) \$USER->id), \$params['type'], \$filter);
        } catch (\\moodle_exception \$e) {
            \$task = (object) ['id' => 1, 'total' => 0];
        }#s;
