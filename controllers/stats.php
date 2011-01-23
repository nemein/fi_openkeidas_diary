<?php
class fi_openkeidas_diary_controllers_stats
{
    private $form = null;

    public function __construct(midgardmvc_core_request $request)
    {
        $this->request = $request;
    }

    private function get_stat($stat, $limit = null, midgard_datetime $since = null)
    {
        $qb = new midgard_query_builder('fi_openkeidas_diary_stat');
        $qb->add_constraint('person', '=', midgardmvc_core::get_instance()->authentication->get_person()->id);
        $qb->add_constraint('stat', '=', $stat);
        $qb->add_order('date', 'DESC');

        if (!is_null($limit))
        {
            $qb->set_limit($limit);
        }

        if (!is_null($since))
        {
            $qb->add_constraint('date', '>', $since);
        }

        $stats = $qb->execute();
        if (empty($stats))
        {
            if ($limit == 1)
            {
                return 0;
            }
            return array(date('d.m.Y') => 0);
        }

        if ($limit == 1)
        {
            return round($stats[0]->value, 1);
        }

        $values = array();
        foreach ($stats as $stat)
        {
            $values[$stat->date->format('d.m.Y')] = round($stat->value, 1);
        }
        return $values;
    }

    public function get_show(array $args)
    {
        midgardmvc_core::get_instance()->authorization->require_user();
        $this->data['stats'] = array
        (
            'bmi' => $this->get_stat('bmi', 1),
            'weight' => $this->get_stat('weight', 1),
            'cooper' => $this->get_stat('cooper', 1),
        );
    }

    public function get_graph(array $args)
    {
        midgardmvc_core::get_instance()->authorization->require_user();

        $since = new midgard_datetime('6 months ago');
        $this->data['stats'] = array
        (
            'bmi' => $this->get_stat('bmi', null, $since),
            'weight' => $this->get_stat('weight', null, $since),
            'cooper' => $this->get_stat('cooper', null, $since),
        );

        midgardmvc_core::get_instance()->component->load_library('Graph');
        $graph = new ezcGraphLineChart();
        foreach ($this->data['stats'] as $name => $stats)
        {
            $graph->data[$this->get_label($name)] = new ezcGraphArrayDataSet(array_reverse($stats, true));
            $graph->data[$this->get_label($name)]->symbol = ezcGraph::BULLET;
        }

        $graph->driver = new fi_openkeidas_diary_graph_gd();
        $graph->driver->options->imageFormat = IMG_PNG;
        $graph->options->font = midgardmvc_core::get_instance()->configuration->graph_font;
        $graph->legend->position = ezcGraph::BOTTOM;
        $graph->palette = new ezcGraphPaletteEz();

        // render image directly to screen
        $graph->renderToOutput(575, 200);

        // wrap up the request
        midgardmvc_core::get_instance()->dispatcher->end_request();
    }

    public function get_update(array $args)
    {
        midgardmvc_core::get_instance()->authorization->require_user();

        $this->data['stats'] = array
        (
            'bmi' => $this->get_stat('bmi', 1),
            'weight' => $this->get_stat('weight', 1),
            'height' => $this->get_stat('height', 1),
            'cooper' => $this->get_stat('cooper', 1),
        );

        $this->load_form();
        $this->data['form'] = $this->form;
    }

    public function post_update(array $args)
    {
        $this->get_update($args);

        $this->form->bmi->set_readonly(false);
        $this->form->process_post();
        $this->form->bmi->set_readonly(true);

        $transaction = new midgard_transaction();
        $transaction->begin();
        foreach ($this->data['stats'] as $name => $value)
        {
            if ($this->form->$name->get_value() == $value)
            {
                continue;
            }

            $stat = new fi_openkeidas_diary_stat();
            $stat->date = new midgard_datetime();
            $stat->person = midgardmvc_core::get_instance()->authentication->get_person()->id;
            $stat->stat = $name;
            $stat->value = $this->form->$name->get_value();
            $stat->create();
            $this->data['stats'][$name] = $this->form->$name->get_value();
        }
        $transaction->commit();
    }

    private function get_label($stat)
    {
        switch ($stat)
        {
            case 'bmi':
                return 'BMI';
            case 'cooper':
                return 'Cooper';
            case 'weight':
                return 'Paino';
            case 'height':
                return 'Pituus';
            default:
                return ucfirst($stat);
        }
    }

    private function load_form()
    {
        $this->form = midgardmvc_helper_forms::create('fi_openkeidas_diary_stats');

        $weight = $this->form->add_field('weight', 'float');
        $weight->set_value($this->data['stats']['weight']);
        $weight_widget = $weight->set_widget('number');
        $weight_widget->set_label('Paino');
        $weight_widget->set_placeholder('Paino (kg)');

        $height = $this->form->add_field('height', 'float');
        $height->set_value($this->data['stats']['height']);
        $height_widget = $height->set_widget('number');
        $height_widget->set_label('Pituus');
        $height_widget->set_placeholder('Pituus (cm)');

        $bmi = $this->form->add_field('bmi', 'float');
        $bmi->set_value($this->data['stats']['bmi']);
        $bmi->set_readonly(true);
        $bmi_widget = $bmi->set_widget('number');
        $bmi_widget->set_label('BMI');

        $cooper = $this->form->add_field('cooper', 'float');
        $cooper->set_value($this->data['stats']['cooper']);
        $cooper_widget = $cooper->set_widget('number');
        $cooper_widget->set_label('Cooper');
        $height_widget->set_placeholder('Cooperin testi (metriä juostu 12 minuutissa)');
    }
}
