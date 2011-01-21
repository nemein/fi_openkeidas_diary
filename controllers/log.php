<?php
class fi_openkeidas_diary_controllers_log extends midgardmvc_core_controllers_baseclasses_crud
{
    private function get_sport($activity_id)
    {
        static $sports = array();
        if (!isset($sports[$activity_id]))
        {
            $sports[$activity_id] = new fi_openkeidas_diary_activity();
            $sports[$activity_id]->get_by_id($activity_id);
        }
        return $sports[$activity_id];
    }

    public function get_list(array $args)
    {
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
        $qb->add_constraint('date', '<', $to->get_value());
        $qb->add_constraint('person', '=', midgardmvc_core::get_instance()->authentication->get_person()->id);
        $qb->add_order('date', 'DESC');
        $entries = $qb->execute();
        $this->data['entries'] = array();
        foreach ($entries as $entry)
        {
            $entry->url = midgardmvc_core::get_instance()->dispatcher->generate_url('log_update', array('entry' => $entry->guid), $this->request);
            $entry->sport = $this->get_sport($entry->activity);
            $this->data['entries'][] = $entry;
        }
    }

    public function load_object(array $args)
    {
        $this->object = new fi_openkeidas_diary_log($args['item']);
        if ($this->object->person != midgardmvc_core::get_instance()->authentication->get_person()->id)
        {
            throw new midgardmvc_exception_unauthorized("You can only access your own logs");
        }
    }
    
    public function prepare_new_object(array $args)
    {
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
        $sport_options = array();
        /*foreach ($categories as $category)
        {
            $category_options[] = array
            (
                'description' => ucfirst($category),
                'value' => $category,
            );
        }*/
        $sport_widget->set_options($sport_options);

        $date = $this->form->add_field('date', 'datetime', true);
        $date->set_value($this->object->date);
        if ($this->object->date->getTimestamp() == 0)
        {
            $date->set_value(new DateTime());
        }
        $date_widget = $date->set_widget('datetime');
        $date_widget->set_label('Päivämäärä');

        $duration = $this->form->add_field('duration', 'float', true);
        $duration->set_value($this->object->duration);
        $duration_widget = $duration->set_widget('number');
        $duration_widget->set_label('Kesto');

        $location = $this->form->add_field('location', 'text');
        $location->set_value($this->object->location);
        $location_widget = $location->set_widget('text');
        $location_widget->set_label('Paikka');
    }

    public function get_url_read()
    {
        return $this->get_url_update();
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
