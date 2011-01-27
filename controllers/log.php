<?php
class fi_openkeidas_diary_controllers_log extends midgardmvc_core_controllers_baseclasses_crud
{
    private function get_sport($activity_id)
    {
        if ($activity_id == 0)
        {
            return 'Tuntematon';
        }
        static $sports = array();
        if (!isset($sports[$activity_id]))
        {
            $sports[$activity_id] = new fi_openkeidas_diary_activity();
            $sports[$activity_id]->get_by_id($activity_id);
        }
        return $sports[$activity_id]->title;
    }

    private function get_sport_options()
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

    public function get_list(array $args)
    {
        midgardmvc_core::get_instance()->authorization->require_user();

        $this->data['form'] = midgardmvc_helper_forms::create('fi_openkeidas_diary_logs');
        $this->data['form']->set_method('get');

        $from = $this->data['form']->add_field('from', 'datetime', true);
        $from_widget = $from->set_widget('date');
        $from_widget->set_label('Mistä');
        if (isset($_GET['from']))
        {
            $from->set_value($_GET['from']);
            $from->validate();
        }
        else
        {
            $from->set_value(new midgard_datetime('30 days ago'));
        }

        $to = $this->data['form']->add_field('to', 'datetime', true);
        $to_widget = $to->set_widget('date');
        $to_widget->set_label('Mihin');
        if (isset($_GET['to']))
        {
            $to->set_value($_GET['to']);
            $to->validate();
        }
        else
        {
            $to->set_value(new midgard_datetime());
        }

        $qb = new midgard_query_builder('fi_openkeidas_diary_log');
        $qb->add_constraint('date', '>', $from->get_value());
        $qb->add_constraint('date', '<=', $to->get_value());
        $qb->add_constraint('person', '=', midgardmvc_core::get_instance()->authentication->get_person()->id);
        $qb->add_order('date', 'DESC');
        $entries = $qb->execute();
        $this->data['entries'] = array();
        foreach ($entries as $entry)
        {
            $entry->update_url = midgardmvc_core::get_instance()->dispatcher->generate_url('log_update', array('entry' => $entry->guid), $this->request);
            $entry->delete_url = midgardmvc_core::get_instance()->dispatcher->generate_url('log_delete', array('entry' => $entry->guid), $this->request);
            $entry->sport = $this->get_sport($entry->activity);
            $this->data['entries'][] = $entry;
        }
    }

    public function load_object(array $args)
    {
        midgardmvc_core::get_instance()->authorization->require_user();
        try {
            $this->object = new fi_openkeidas_diary_log($args['entry']);
        }
        catch (midgard_error_exception $e)
        {
            throw new midgardmvc_exception_notfound($e->getMessage());
        }

        if ($this->object->person != midgardmvc_core::get_instance()->authentication->get_person()->id)
        {
            throw new midgardmvc_exception_unauthorized("You can only access your own logs");
        }
    }
    
    public function prepare_new_object(array $args)
    {
        midgardmvc_core::get_instance()->authorization->require_user();
        $this->object = new fi_openkeidas_diary_log();
        $this->object->person = midgardmvc_core::get_instance()->authentication->get_person()->id;
    }

    public function load_form()
    {
        $this->form = midgardmvc_helper_forms::create('fi_openkeidas_diary_log');

        $sport = $this->form->add_field('activity', 'integer');
        $sport->set_value($this->object->activity);
        $sport_widget = $sport->set_widget('selectoption');
        $sport_widget->set_label('Laji');
        $sport_widget->set_options($this->get_sport_options());

        $date = $this->form->add_field('date', 'datetime', true);
        $object_date = $this->object->date;
        if ($object_date->getTimestamp() <= 0)
        {
            $object_date->setTimestamp(time());
        }
        $date->set_value($object_date);
        $date_widget = $date->set_widget('date');
        $date_widget->set_label('Päivämäärä');

        $duration = $this->form->add_field('duration', 'float', true);
        $duration->set_value($this->object->duration);
        $duration_widget = $duration->set_widget('number');
        $duration_widget->set_label('Aika tunteina (esim. 0.5)');

        $location = $this->form->add_field('location', 'text');
        $location->set_value($this->object->location);
        $location_widget = $location->set_widget('text');
        $location_widget->set_label('Paikka');
    }

    public function get_url_read()
    {
        return midgardmvc_core::get_instance()->dispatcher->generate_url('index', array(), $this->request);
    }

    public function get_url_update()
    {
        return midgardmvc_core::get_instance()->dispatcher->generate_url
        (
            'log_update', array
            (
                'entry' => $this->object->guid
            ),
            $this->request
        );
    }
}
?>
