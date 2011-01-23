<?php
class fi_openkeidas_diary_graph_gd extends ezcGraphGdDriver
{
    public function renderToOutput()
    {
        // Send headers via Midgard MVC so this works in AppServer
        midgardmvc_core::get_instance()->dispatcher->header('Content-Type: ' . $this->getMimeType());

        $this->render(null);
    }
}
