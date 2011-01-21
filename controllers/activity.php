<?php
class fi_openkeidas_diary_controllers_activity
{
    public function __construct(midgardmvc_core_request $request)
    {
        $this->request = $request;
    }

    public function get_top(array $args)
    {
        $qb = new midgard_query_builder('fi_openkeidas_diary_activity');
        $qb->set_limit(5);
        $qb->add_order('metadata.score', 'DESC');
        $this->data['sports'] = $qb->execute();
    }
}
