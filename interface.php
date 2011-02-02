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
        midgard_object_class::connect_default('fi_openkeidas_diary_log', 'action-created', array('fi_openkeidas_diary', 'update_sport_and_group'), array($request));
        midgard_object_class::connect_default('fi_openkeidas_diary_challenge', 'action-created', array('fi_openkeidas_diary', 'update_challenge_members'), array($request));
        $connected = true;
    }

    public static function update_challenge_members(fi_openkeidas_diary_challenge $challenge, $params)
    {
        if (!$challenge->challenger)
        {
            return;
        }

        midgardmvc_core::get_instance()->authorization->enter_sudo('fi_openkeidas_diary');
        $participant = new fi_openkeidas_diary_challenge_participant();
        $participant->grp = $challenge->challenger;
        $participant->challenge = $challenge->id;
        $participant->create();
        $participant->approve();
        midgardmvc_core::get_instance()->authorization->leave_sudo();
    }

    public static function update_sport_and_group(fi_openkeidas_diary_log $log, $params)
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

        $qb = new midgard_query_builder('fi_openkeidas_groups_group_member');
        $qb->add_constraint('person', '=', midgardmvc_core::get_instance()->authentication->get_person()->id);
        $qb->add_constraint('metadata.isapproved', '=', true);
        $memberships = $qb->execute();
        foreach ($memberships as $membership)
        {
            $group = new fi_openkeidas_groups_group($membership->grp);
            $group->metadata->score++;
            $group->update();
        }
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

    public static function group_average_duration_this_week(fi_openkeidas_groups_group $group)
    {
        midgardmvc_core::get_instance()->authorization->require_user();
        $mc = new midgard_collector('fi_openkeidas_groups_group_member', 'grp', $group->id);
        $mc->add_constraint('metadata.isapproved', '=', true);
        $mc->set_key_property('person');
        $mc->execute();
        $member_ids = array_keys($mc->list_keys());
        $member_count = count($member_ids);
        if ($member_count == 0)
        {
            return 0;
        }

        $total = 0;
        $qb = new midgard_query_builder('fi_openkeidas_diary_log');
        $qb->add_constraint('person', 'IN', $member_ids);
        $qb->add_constraint('date', '>', new midgard_datetime('1 week ago'));
        $qb->add_constraint('date', '<', new midgard_datetime());
        $entries = $qb->execute();
        foreach ($entries as $entry)
        {
            $total += $entry->duration;
        }
        return round($total / $member_count, 1);
    }
}
