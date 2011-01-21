<?php
class fi_openkeidas_diary
{
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
