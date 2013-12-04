<?php

namespace Snidely;

abstract class Compiler {

    /// Properties ///

    public $indent = 0;

    /**
     *
     * @var Snidely
     */
    public $snidely;

    /**
     * A list of helpers that will produce specific compiler output.
     *
     * @var array
     */
    protected $compileHelpers = array();

    /// Methods ///

    public function compile($nodes) {
        return $this->compileNodes($nodes);
    }

    protected function compileNodes($nodes, $indent = 0) {
        $result = '';

        foreach ($nodes as $node) {
            $type = $node[Tokenizer::TYPE];
            $name = isset($node[Tokenizer::NAME]) ? $node[Tokenizer::NAME] : false;

            if (isset($this->snidely->helpers[$name])) {
                // There is a helper.
                $result .= $this->helper($node, $indent, $this->snidely->helpers[$name]);
                continue;
            } elseif (isset($this->compileHelpers[$name])) {
                // There is a specific compile helper.

                $callback = $this->compileHelpers[$name];

                if ($type == Tokenizer::T_SECTION)
                    $result .= call_user_func($callback, $node, $indent, $this);
                else
                    $result .= call_user_func($callback, $node, $indent, $this);

                continue;
            }

            switch ($type) {
                case Tokenizer::T_COMMENT:
                    $result .= $this->comment($node, $indent);
                    break;
                case Tokenizer::T_TEXT:
                    $result .= $this->text($node, $indent);
                    break;
                case Tokenizer::T_ESCAPED:
                    $result .= $this->escaped($node, $indent);
                    break;
                case Tokenizer::T_UNESCAPED:
                    $result .= $this->unescaped($node, $indent);
                    break;
                case Tokenizer::T_SECTION:
                    $result .= $this->section($node, $indent);
                    break;
                case Tokenizer::T_INVERTED:
                    $result .= $this->inverted($node, $indent);
                    break;
                case Tokenizer::T_PARTIAL:
                case Tokenizer::T_PARTIAL_2:
                    $result .= $this->partial($node, $indent);
                    break;
                case Tokenizer::T_DELIM_CHANGE:
                    // Do nothing, the tokenizer took care of this.
                    break;
                default:
                    $result .= $this->unknown($node, $indent);
            }
        }
        return $result;
    }

    public function comment($node, $indent) {
        // Do nothing for comments.
    }

    public function escaped($node, $indent) {
        return $this->uknown($node, $indent);
    }

    protected function getSnidely($node, $indent, $comment = true) {
        // Figure out the brackets.
        switch ($node[Tokenizer::TYPE]) {
            case Tokenizer::T_ESCAPED:
                $lbracket = '{{';
                $rbracket = '}}';
                break;
            case Tokenizer::T_UNESCAPED:
                $lbracket = '{{{';
                $rbracket = '}}}';
                break;
            case Tokenizer::T_SECTION:
                $lbracket = '{{#';
                $rbracket = '}}';
                break;
            default:
                return '';
        }

        // Go through the args.
        $fargs = array();
        foreach ($node[Tokenizer::ARGS] as $arg) {
            $fargs[] = $this->getSnidelyArg($arg);
        }

        // Go through the hash.
        if (isset($node[Tokenizer::HASH]) && is_array($node[Tokenizer::HASH])) {
            foreach ($node[Tokenizer::HASH] as $key => $arg) {
                $fargs[] = "$key=" . $this->getSnidelyArg($arg);
            }
        }

        $result = $lbracket . implode(' ', $fargs) . $rbracket;

        if ($comment) {
            $result = $this->str() . $this->indent($indent) . '// ' . $result;
        }

        return $result;
    }

    protected function getSnidelyArg($arg) {
        $px = '';
        $farg_parts = array();
        foreach ($arg as $arg_part) {
            switch ($arg_part[Tokenizer::TYPE]) {
                case Tokenizer::T_VAR:
                    $farg_parts[] = $arg_part[Tokenizer::VALUE];
                    break;
                case Tokenizer::T_STRING:
                    $farg_parts[] = '"' . $arg_part[Tokenizer::VALUE] . '"';
                    break;
                case Tokenizer::T_DOT:
                    $px = $arg_part[Tokenizer::VALUE] . '/';
                    break;
            }
        }

        return $px . implode('.', $farg_parts);
    }

    public function helper($node, $indent, $helper) {
        return $this->unknown($node, $indent);
    }

    public function indent($indent) {
        $indent = str_repeat('    ', $indent);
        return $indent;
    }

    public function inverted($node, $indent) {
        return $this->unknown($node, $indent);
    }

    public function partial($node, $indent) {
        return $this->unknown($node, $indent);
    }

    public function registerCompileHelper($name, $callback) {
        $this->compileHelpers[$name] = $callback;
    }

    public function reset() {

    }

    public function section($node, $indent) {
        return $this->unknown($node, $indent);
    }

    /**
     * Strip the text nodes around comments.
     *
     * @param type $nodes
     */
    protected function stripStandalone($nodes, $edges = false) {
        $unset = array();

        if ($edges && count($nodes) > 0) {
            // Trim an empty line at the beginning.
            if (isset($nodes[0])
                && $nodes[0][Tokenizer::TYPE] === Tokenizer::T_TEXT
                && substr($nodes[0][Tokenizer::VALUE], -1) === "\n"
                && trim($nodes[0][Tokenizer::VALUE]) === '') {

                $unset[0] = true;
            }

            // Trim empty leading space at the end.
            $l = count($nodes) - 1;
            if ($nodes[$l][Tokenizer::TYPE] === Tokenizer::T_TEXT
                && substr($nodes[$l][Tokenizer::VALUE], -1) !== "\n"
                && trim($nodes[$l][Tokenizer::VALUE]) === '') {

                $unset[$l] = true;
            }
        }

        // Loop through the nodes and figure out which comments to strip.
        foreach ($nodes as $i => &$node) {
            if (in_array($node[Tokenizer::TYPE], array(Tokenizer::T_COMMENT, Tokenizer::T_SECTION, Tokenizer::T_INVERTED, Tokenizer::T_DELIM_CHANGE ))) {
                // Strip empty text before the comment.
                $j = $i - 1;
                if (isset($nodes[$j])) {
                    if ($nodes[$j][Tokenizer::TYPE] === Tokenizer::T_TEXT) {
                        $value = $nodes[$j][Tokenizer::VALUE];
                        if (substr($value, -1) !== "\n") {
                            if (trim($value) === '') {
                                // Remove empty lines.
                                $unset[$j] = true;
                            } else {
                                // This is an inline comment. Don't trim.
                                continue;
                            }
                        }
                    }
                }
                // Strip empty text after the comment.
                $h = $i + 1;
                if (isset($nodes[$h])) {
                    if ($nodes[$h][Tokenizer::TYPE] === Tokenizer::T_TEXT) {
                        $value = $nodes[$h][Tokenizer::VALUE];
                        if (substr($value, -1) === "\n" && trim($value) === '') {
                            // Remove empty lines.
                            $unset[$h] = true;
                        }
                    } else {
                        unset($unset[$j]);
                    }
                }
            }

            if (isset($node[Tokenizer::NODES]) && is_array($node[Tokenizer::NODES])) {
                $node[Tokenizer::NODES] = $this->stripStandalone($node[Tokenizer::NODES], true);
            }
        }
        // Now that we've gathered the unset indexes unset the nodes.
        foreach ($unset as $i => $v) {
            unset($nodes[$i]);
        }
        return array_values($nodes);
    }

    public function text($node, $indent) {
        return $this->unknown($node, $indent);
    }

    abstract function unknown($node, $indent);

    public function unescaped($node, $indent) {
        return $this->unknown($node, $indent);
    }

}