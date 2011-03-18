<?php
class fi_openkeidas_diary_controllers_challenge extends midgardmvc_core_controllers_baseclasses_crud
{
    public function load_object(array $args)
    {
        midgardmvc_core::get_instance()->authorization->require_user();

        $this->data['user_groups'] = $this->get_user_groups();

        try {
            $this->object = new fi_openkeidas_diary_challenge($args['challenge']);
        }
        catch (midgard_error_exception $e)
        {
            throw new midgardmvc_exception_notfound($e->getMessage());
        }
    }

    private function is_challenger()
    {
        foreach ($this->data['user_groups'] as $group)
        {
            if ($group->id == $this->object->challenger)
            {
                return true;
            }
        }
        return false;
    }

    private function get_user_groups()
    {
        $qb = new midgard_query_builder('fi_openkeidas_groups_group_member');
        //$qb->add_constraint('admin', '=', true);
        $qb->add_constraint('metadata.isapproved', '=', true);
        $qb->add_constraint('person', '=', midgardmvc_core::get_instance()->authentication->get_person()->id);
        return array_map
        (
            function ($membership)
            {
                return new fi_openkeidas_groups_group($membership->grp);
            },
            $qb->execute()
        );
    }

    public function prepare_new_object(array $args)
    {
        midgardmvc_core::get_instance()->authorization->require_user();

        $this->data['user_groups'] = $this->get_user_groups();
        if (empty($this->data['user_groups']))
        {
            throw new midgardmvc_exception_notfound("Only group members can challenge");
        }

        $this->object = new fi_openkeidas_diary_challenge();
    }

    public function get_read(array $args)
    {
        parent::get_read($args);

        $this->data['groups'] = array();

        $this->data['is_challenger'] = $this->is_challenger();
        if ($this->data['is_challenger'])
        {
            $this->data['groups_url'] = midgardmvc_core::get_instance()->dispatcher->generate_url('index', array(), 'fi_openkeidas_groups');
            $this->data['update_url'] = $this->get_url_update();
            $this->data['delete_url'] = midgardmvc_core::get_instance()->dispatcher->generate_url('challenge_delete', array('challenge' => $this->object->guid), $this->request);
        }

        $this->data['challenger'] = new fi_openkeidas_groups_group($this->object->challenger);

        $mc = new midgard_collector('fi_openkeidas_diary_challenge_participant', 'challenge', $this->object->id);
        //$mc->add_constraint('metadata.isapproved', '=', true);
        $mc->set_key_property('grp');
        $mc->execute();
        $grp_ids = array_keys($mc->list_keys());
        if (count($grp_ids) == 0)
        {
            return;
        }

        $qb = new midgard_query_builder('fi_openkeidas_groups_group');
        $qb->add_constraint('id', 'IN', $grp_ids);
        $qb->add_order('metadata.score', 'DESC');
        $groups = $qb->execute();
        foreach ($groups as $group)
        {
            $group->url = midgardmvc_core::get_instance()->dispatcher->generate_url('group_read', array('group' => $group->guid), 'fi_openkeidas_groups');
            $group->activity = fi_openkeidas_diary::group_average_duration_this_week($group);
            $this->data['groups'][] = $group;
        }
    }

    public function load_form()
    {
        $this->form = midgardmvc_helper_forms::create('fi_openkeidas_diary_challenge');
        $title = $this->form->add_field('title', 'text', true);
        $title->set_value($this->object->title);
        $title_widget = $title->set_widget('text');
        $title_widget->set_label('Haaste');

        $group_options = array();
        foreach ($this->data['user_groups'] as $group)
        {
            $group_options[] = array
            (
                'description' => $group->title,
                'value' => $group->id,
            );
        }

        $challenger = $this->form->add_field('challenger', 'integer');
        $challenger->set_value($this->object->challenger);
        $challenger_widget = $challenger->set_widget('selectoption');
        $challenger_widget->set_label('Haastaja');
        $challenger_widget->set_options($group_options);

        $start = $this->form->add_field('start', 'datetime', true);
        $object_start = $this->object->start;
        if ($object_start->getTimestamp() <= 0)
        {
            $object_start->setTimestamp(time());
        }
        $start->set_value($object_start);
        $start_widget = $start->set_widget('date');
        $start_widget->set_label('Haaste alkaa');

        $end = $this->form->add_field('enddate', 'datetime', true);
        $object_end = $this->object->enddate;
        if ($object_end->getTimestamp() <= 0)
        {
            $new_end = new DateTime('last day of next month');
            $object_end->setTimestamp($new_end->getTimestamp());
        }
        $end->set_value($object_end);
        $end_widget = $end->set_widget('date');
        $end_widget->set_label('Haaste päättyy');
    }

    public function post_challenge(array $args)
    {
        $this->load_object($args);

        try
        {
            $grp = new fi_openkeidas_groups_group($args['group']);
        }
        catch (midgard_error_exception $e)
        {
            throw new midgardmvc_exception_notfound($e->getMessage());
        }

        if (!$this->is_challenger())
        {
            throw new midgardmvc_exception_unauthorized("Only challenger can challenge");
        }

        midgardmvc_core::get_instance()->authorization->enter_sudo('fi_openkeidas_diary');
        $participant = new fi_openkeidas_diary_challenge_participant();
        $participant->grp = $grp->id;
        $participant->challenge = $this->object->id;
        $participant->create();
        midgardmvc_core::get_instance()->authorization->leave_sudo();

        midgardmvc_core::get_instance()->head->relocate($this->get_url_read());
    }

    public function post_accept(array $args)
    {
        $this->load_object($args);

        try
        {
            $participant = new fi_openkeidas_diary_challenge_participant($args['participant']);
        }
        catch (midgard_error_exception $e)
        {
            throw new midgardmvc_exception_notfound($e->getMessage());
        }

        $found = false;
        foreach ($this->data['user_groups'] as $group)
        {
            if ($group->id == $participant->grp)
            {
                $found = true;
            }
        }
        if (!$found)
        {
            throw new midgardmvc_exception_notfound("Your group hasn't been challenged");
        }

        midgardmvc_core::get_instance()->authorization->enter_sudo('fi_openkeidas_diary');
        $participant->approve();
        midgardmvc_core::get_instance()->authorization->leave_sudo();

        midgardmvc_core::get_instance()->head->relocate($this->get_url_read());
    }

    public function get_url_read()
    {
        return midgardmvc_core::get_instance()->dispatcher->generate_url
        (
            'challenge_read', array
            (
                'challenge' => $this->object->guid
            ),
            $this->request
        );
    }

    public function get_url_update()
    {
        return midgardmvc_core::get_instance()->dispatcher->generate_url
        (
            'challenge_update', array
            (
                'challenge' => $this->object->guid
            ),
            $this->request
        );
    }
}
