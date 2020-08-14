<?php
namespace Stack;

class MetricsRegistry
{
    public function __construct($metrics = [])
    {
        $storage = function_exists('apcu_enabled') && apcu_enabled()
            ? new \Prometheus\Storage\APC()
            : new \Prometheus\Storage\InMemory();
        $this->registry = new \Prometheus\CollectorRegistry($storage);
        $this->registerMetrics($metrics);
    }

    public function registerMetrics($metrics = [])
    {
        foreach ($metrics as $metric => [$kind, $description, $labels]) {
            [$namespace, $name] = explode('.', $metric);

            switch ($kind) {
                case 'histogram':
                    $this->metrics[$metric] = $this->registry->getOrRegisterHistogram(
                        $namespace,
                        $name,
                        $description,
                        $labels
                    );
                    break;

                case 'counter':
                    $this->metrics[$metric] = $this->registry->getOrRegisterCounter(
                        $namespace,
                        $name,
                        $description,
                        $labels
                    );
                    break;

                case 'gauge':
                    $this->metrics[$metric] = $this->registry->getOrRegisterGauge(
                        $namespace,
                        $name,
                        $description,
                        $labels
                    );
                    break;
            }
        }
    }

    public function getGauge($metric)
    {
        return $this->metrics[$metric];
    }

    public function getCounter($metric)
    {
        return $this->metrics[$metric];
    }

    public function getHistogram($metric)
    {
        return $this->metrics[$metric];
    }

    public function render()
    {
        $renderer = new \Prometheus\RenderTextFormat();
        $result = $renderer->render($this->registry->getMetricFamilySamples());

        header('Content-type: ' . \Prometheus\RenderTextFormat::MIME_TYPE);
        header('Cache-Control: no-cache,max-age=0');

        echo $result;
        exit();
    }
}
