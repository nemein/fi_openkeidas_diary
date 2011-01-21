<?php
class fi_openkeidas_diary
{
    public function inject_process(midgardmvc_core_request $request)
    {
        static $connected = false;
        if ($connected)
        {
            return;
        }
        // Subscribe to content changed signals from Midgard
        midgard_object_class::connect_default('fi_openkeidas_diary_log', 'action-created', array('fi_openkeidas_diary', 'update_sport'), array($request));
        $connected = true;
    }

    public static function update_sport(fi_openkeidas_diary_log $log, $params)
    {
        if (!$log->activity)
        {
            return;
        }

        midgardmvc_core::get_instance()->authorization->enter_sudo('fi_openkeidas_diary'); 
        $activity = new fi_openkeidas_diary_activity();
        $activity->get_by_id($log->activity);
        $activity->metadata->score++;
        $activity->update();
        midgardmvc_core::get_instance()->authorization->leave_sudo();
    }

    public static function duration_this_week()
    {
        midgardmvc_core::get_instance()->authorization->require_user();

        $value = 0;
        $qb = new midgard_query_builder('fi_openkeidas_diary_log');
        $qb->add_constraint('date', '>', new midgard_datetime('1 week ago'));
        $qb->add_constraint('date', '<', new midgard_datetime());
        $qb->add_constraint('person', '=', midgardmvc_core::get_instance()->authentication->get_person()->id);
        $entries = $qb->execute();
        foreach ($entries as $entry)
        {
            $value += $entry->duration;
        }
        return $value;
    }
}
