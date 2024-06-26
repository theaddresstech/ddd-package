<?php

namespace theaddresstechnology\DDD\Helper\Make\Types;

use theaddresstechnology\DDD\Helper\FileCreator;
use theaddresstechnology\DDD\Helper\Make\Maker;
use theaddresstechnology\DDD\Helper\NamespaceCreator;
use theaddresstechnology\DDD\Helper\Naming;
use theaddresstechnology\DDD\Helper\Path;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class Listener extends Maker
{
    /**
     * Options to be available once Command-Type is called
     *
     * @return Array
     */
    public $options = [
        'name',
        'domain',
        'command_http_general',
        'event',
    ];

    /**
     * Return options that should be treated as choices
     *
     * @return Array
     */
    public $allowChoices = [
        'domain',
        'command_http_general',
        'event',
    ];

    /**
     * Check if the current options is True/False question
     *
     * @return Array
     */
    public $booleanOptions = [];

    /**
     * Check if the current options is requesd based on other option
     *
     * @return Array
     */
    public $requiredUnless = [];

    /**
     * Fill all placeholders in the stub file
     *
     * @param array $values
     * @return boolean
     */
    public function service(Array $values = []):bool{

        $name = Naming::class($values['name']);
        $type = $values['command_http_general'];
        $placeholders = [
            '{{DOMAIN}}' => $values['domain'],
            '{{NAME}}' => $name,
            '{{EVENT_NAME}}' => $values['event'],
            '{{TYPE}}' => $type
        ];

        $className = $name.'Listener';

        $destination = Path::toDomain($values['domain'],'Listeners',$type);

        $content = Str::of($this->getStub('listener'))
                        ->replace(array_keys($placeholders),array_values($placeholders));

        $this->save($destination,$className,'php',$content);

        preg_match('#namespace (Src\\\Domain\\\.*);#',$content,$matches);

        $class = $matches[1]."\\".$className;

        $eventServiceProviderPath = Path::toDomain($values['domain'],'Providers','EventServiceProvider.php');
        $event_name = $values['event'];

        $eventServiceProviderContent = Str::of(File::get($eventServiceProviderPath))->replace(
            "###LISTENERS_{$type}_$event_name###",
            "\\$class::class,\n\t\t\t\t###LISTENERS_{$type}_$event_name###\n\t\t"
        );

        File::put($eventServiceProviderPath,$eventServiceProviderContent);

        return true;
    }

}
