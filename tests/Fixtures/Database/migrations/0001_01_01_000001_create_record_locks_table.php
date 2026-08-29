<?php

/*
 * The package's own migration, run after the fixtures so the users table exists
 * for it to constrain against -- which is the whole point of exercising it here
 * rather than hand-writing an equivalent table for the tests.
 */

return include __DIR__ . '/../../../../database/migrations/create_record_locks_table.php.stub';
