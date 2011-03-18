<?php
class fi_openkeidas_diary_controllers_log extends midgardmvc_core_controllers_baseclasses_crud
{
    public function get_list(array $args)
    {
        midgardmvc_core::get_instance()->authorization->require_user();

        $this->data['form'] = $this->get_list_form();
        
        $activity = null;
        $activity_filter = $this->data['form']->sport->get_value();
        if ($activity_filter)
        {
            $activity = fi_openkeidas_diary_activities::get($activity_filter);
        }

        $request = $this->request;
        $this->data['entries'] = array_map
        (
            function ($entry) use ($request)
            {
                $entry->update_url = midgardmvc_core::get_instance()->dispatcher->generate_url('log_update', array('entry' => $entry->guid), $request);
                $entry->delete_url = midgardmvc_core::get_instance()->dispatcher->generate_url('log_delete', array('entry' => $entry->guid), $request);
                $entry->sport = fi_openkeidas_diary_activities::get_title($entry->activity);
                return $entry;
            },
            fi_openkeidas_diary_logs::within
            (
                $this->data['form']->from->get_value(),
                $this->data['form']->to->get_value(),
                array(midgardmvc_core::get_instance()->authentication->get_person()->id),
                $activity
            )
        );
    }
    
    private function get_list_form()
    {
        $form = midgardmvc_helper_forms::create('fi_openkeidas_diary_logs');
        $form->set_method('get');

        $from = $form->add_field('from', 'datetime', true);
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

        $to = $form->add_field('to', 'datetime', true);
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

        $sport = $form->add_field('sport', 'integer');
        if (isset($_GET['sport']))
        {
            $sport->set_value($_GET['sport']);
            $sport->validate();
        }
        $sport_widget = $sport->set_widget('selectoption');
        $sport_widget->set_label('Laji');
        $options = fi_openkeidas_diary_activities::get_options();
        array_unshift
        (
            $options,
            array
            (
                'description' => 'Kaikki',
                'value' => '',
            )
        );
        $sport_widget->set_options($options);
        
        return $form;
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
        $sport_widget->set_options(fi_openkeidas_diary_activities::get_options());

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

        $distance = $this->form->add_field('distance', 'float');
        $distance->set_value($this->object->distance);
        $distance_widget = $distance->set_widget('number');
        $distance_widget->set_label('Kilometrit (esim. 3.5)');

        $location = $this->form->add_field('location', 'text');
        $location->set_value($this->object->location);
        $location_widget = $location->set_widget('text');
        $location_widget->set_label('Paikka');
        
        $comment = $this->form->add_field('comment', 'text');
        $comment->set_value($this->object->comment);
        $comment_widget = $comment->set_widget('textarea');
        $comment_widget->set_label('Lisätiedot');
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
