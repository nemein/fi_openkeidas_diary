<?php
class fi_openkeidas_diary_activities
{
    public static function get($activity_id)
    {
        $activity_id = (int) $activity_id;
        static $sports = array();
        if (!isset($sports[$activity_id]))
        {
            $sports[$activity_id] = new fi_openkeidas_diary_activity();
            $sports[$activity_id]->get_by_id($activity_id);
        }
        return $sports[$activity_id];
    }

    public static function get_title($activity_id)
    {
        if ($activity_id == 0)
        {
            return 'Tuntematon';
        }
        return self::get($activity_id)->title;
    }

    public static function get_options()
    {
        $config_sports = midgardmvc_core::get_instance()->configuration->sports;
        $options = array();
        $mc = new midgard_collector('fi_openkeidas_diary_activity', 'metadata.deleted', false);
        $mc->set_key_property('title');
        $mc->add_value_property('id');
        $mc->execute();
        $sports = $mc->list_keys();
        foreach ($config_sports as $sport)
        {
            if (!isset($sports[$sport]))
            {
                // Sync to DB
                midgardmvc_core::get_instance()->authorization->enter_sudo('fi_openkeidas_diary'); 
                $db_sport = new fi_openkeidas_diary_activity();
                $db_sport->title = $sport;
                $db_sport->create();
                $options[] = array
                (
                    'description' => $sport,
                    'value' => $db_sport->id,
                );
                midgardmvc_core::get_instance()->authorization->leave_sudo();
                continue;
            }

            $options[] = array
            (
                'description' => $sport,
                'value' => $mc->get_subkey($sport, 'id'),
            );
        }
        return $options;
    }
}
