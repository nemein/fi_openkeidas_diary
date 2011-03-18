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
        midgard_object_class::connect_default('fi_openkeidas_diary_challenge', 'action-delete', array('fi_openkeidas_diary', 'remove_challenge_members'), array($request));
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

    public static function remove_challenge_members(fi_openkeidas_diary_challenge $challenge, $params)
    {
        midgardmvc_core::get_instance()->authorization->enter_sudo('fi_openkeidas_diary');

        $qb = new midgard_query_builder('fi_openkeidas_diary_challenge_participant');
        $qb->add_constraint('challenge', '=', $challenge->id);
        $participants = $qb->execute();
        foreach ($participants as $participant)
        {
            $participant->delete();
        }

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
}
