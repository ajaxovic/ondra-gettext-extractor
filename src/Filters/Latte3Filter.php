<?php
declare(strict_types=1);
/**
 * @copyright Copyright (c) 2023 Ondřej Nevřela <email: ondra.nevrela@gmail.com>
 * @license New BSD License
 */

namespace Vodacek\GettextExtractor\Filters;

use Jolanda\Latte\Macros\TranslateNode;
use Latte\Compiler\TemplateLexer;
use Latte\Compiler\TokenStream;
use Nette\Utils\FileSystem;
use Latte\Compiler\Nodes\FragmentNode;
use Latte\Compiler\Nodes\Html\ElementNode;
use Latte\Compiler\Nodes\Php\Scalar\StringNode;
use Latte;
use Latte\Engine;
use Latte\Compiler\TemplateParser;

class Latte3Filter extends AFilter implements IFilter {
    public array $nodes;
    public Engine $engine;

    public function __construct(Engine $engine, array $nodes) {
        $this->nodes = $nodes;
        $this->engine = $engine;

        $this->initEngine();
    }

    private function initEngine(){
        $this->engine->setStrictParsing(false);
    }

    private function initParser(): TemplateParser
    {
        $parser = new TemplateParser();

        foreach ($this->engine->getExtensions() as $extension){
            $extension->beforeCompile($this->engine);
            $parser->addTags($extension->getTags());
        }

        $parser->setPolicy($this->engine->getPolicy(true));

        return $parser;
    }

    public function extract(string $file): array {
        $data = [];

        $parser = $this->initParser();
        $node = $parser->parse(FileSystem::read($file));
        $this->engine->applyPasses($node);

        if($node->head){
            $this->processFragmentNode($node->head, $data);
        }

        if($node->main){
            $this->processFragmentNode($node->main, $data);
        }

        return $data;
    }

    private function processFragmentNode(FragmentNode $node, array &$data){
        foreach ($node->children as $child){
            $this->processNode($child, $data);
        }
    }

    private function processNode($node, array &$data){
        if($node instanceof ElementNode){
            if($node->attributes){
                if($node->attributes instanceof FragmentNode){
                    $this->processFragmentNode($node->attributes, $data);
                }else{
                    $this->processNode($node->attributes, $data);
                }
            }

            if($node->content){
                foreach ($node->content as $n){
                    if($n instanceof FragmentNode){
                        $this->processFragmentNode($n, $data);
                    }else{
                        $this->processNode($n, $data);
                    }
                }
            }
        }else if($node instanceof FragmentNode){
            $this->processFragmentNode($node);
        }else if($node instanceof Latte\Compiler\Node){
            $vars = get_object_vars($node);

            if($vars){
                foreach ($vars as $v){
                    if($v instanceof FragmentNode){
                        $this->processFragmentNode($v, $data);
                    }else if($v instanceof Latte\Compiler\Node){
                        $this->processNode($v, $data);
                    }
                }
            }
        }

        $isTranslateNode = false;
        foreach ($this->nodes as $n){
            if($node instanceof $n){
                $isTranslateNode = true;
                break;
            }
        }

        if(!$isTranslateNode){
            return;
        }

        if(isset($node->subject) && $node->subject instanceof StringNode){
            $data[] = [
                'singular' => $node->subject->value,
                'line' => $node->position->line
            ];
        }


    }

}
