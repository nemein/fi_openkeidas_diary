<?php
class fi_openkeidas_diary_logs
{   
    public static function within(DateTime $from, DateTime $to, array $users, fi_openkeidas_diary_activity $activity = null)
    {
        if (empty($users))
        {
            return array();
        }
        $qb = new midgard_query_builder('fi_openkeidas_diary_log');
        $qb->add_constraint('date', '>', $from);
        $qb->add_constraint('date', '<', $to);
        $qb->add_constraint('person', 'IN', $users);
        
        if (!is_null($activity))
        {
            $qb->add_constraint('activity', '=', $activity->id);
        }
        
        return $qb->execute();
    }
 
    public static function duration_within(DateTime $from, DateTime $to, array $users, fi_openkeidas_diary_activity $activity = null)
    {
        return array_reduce
        (
            self::within($from, $to, $users, $activity),
            function ($totals, fi_openkeidas_diary_log $entry)
            {
                return $totals + $entry->duration;
            },
            0
        );
    }
    
    public static function average_duration_within(DateTime $from, DateTime $to, array $users, fi_openkeidas_diary_activity $activity = null)
    {
        return self::duration_within
        (
            $from,
            $to,
            $users,
            $activity
        ) / count($users);
    }
    
    public static function group_duration_within(DateTime $from, DateTime $to, fi_openkeidas_groups_group $group, fi_openkeidas_diary_activity $activity = null)
    {
        midgardmvc_core::get_instance()->authorization->require_user();
        
        $mc = new midgard_collector('fi_openkeidas_groups_group_member', 'grp', $group->id);
        $mc->add_constraint('metadata.isapproved', '=', true);
        $mc->set_key_property('person');
        $mc->execute();
        return self::average_duration_within
        (
            $from,
            $to,
            array_keys($mc->list_keys()),
            $activity
        );
    }  

    public static function user_duration_this_week(fi_openkeidas_diary_activity $activity = null)
    {
        midgardmvc_core::get_instance()->authorization->require_user();

        return self::duration_within
        (
            new midgard_datetime('-1 week'),
            new midgard_datetime(),
            array(midgardmvc_core::get_instance()->authentication->get_person()->id),
            $activity
        );
    }

    public static function group_duration_this_week(fi_openkeidas_groups_group $group, fi_openkeidas_diary_activity $activity = null)
    {
        return self::group_duration_within
        (
            new midgard_datetime('-1 week'),
            new midgard_datetime(),
            $group,
            $activity
        );
    }
}
