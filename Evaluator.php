<?php

/**
 * Created by PhpStorm.
 * Author: Jonathan Alberth Quispe Fuentes
 * Email: qf.jonathan@gmail.com
 * Date: 06/12/16
 * Time: 06:18 PM
 */
class Evaluator
{
    private $functions = [];
    private $evaluate = null;
    private $expression = '';
    private $index = 0;
    private $variables = [];

    public function __construct($expression = '0')
    {
        $this->functions = [
            'sin' => function ($a) {
                return sin($a);
            },
            'cos' => function ($a) {
                return cos($a);
            },
            'tan' => function ($a) {
                return tan($a);
            },
            'acos' => function ($a) {
                return acos($a);
            },
            'asin' => function ($a) {
                return asin($a);
            },
            'atan' => function ($a) {
                return atan($a);
            },
            'pow' => function ($a, $b) {
                return pow($a, $b);
            }
        ];
        $this->setExpression($expression);
    }

    public function setExpression($expression)
    {
        $this->expression = trim($expression);
        $this->getEvaluation(false);
    }

    public function setVariable($var_key, $value = 0)
    {
        if (is_array($var_key)) {
            $this->variables = array_merge($this->variables, $var_key);
        } else {
            $this->variables[$var_key] = $value;
        }
    }

    public function evaluate(Array $vars = [], Array $functions = [])
    {
        $this->setVariable($vars);
        $this->setFunction($functions);
        return $this->getEvaluation(true);
    }

    private function getEvaluation($evaluate)
    {
        $this->evaluate = $evaluate;
        $this->index = 0;
        $a = $this->expression();
        if ($this->nextChar() != '') {
            throw new Exception($this->error('Se esperaba fin de expresion'));
        }
        return $a;
    }

    private function nextChar($avoid_blank = true)
    {
        if ($avoid_blank) {
            while ($this->index < strlen($this->expression) &&
                ($this->expression[$this->index] == ' ' ||
                    $this->expression[$this->index] == '\t')) {
                $this->index++;
            }
        }
        if ($this->index >= strlen($this->expression)) {
            return '';
        }
        return $this->expression[$this->index++];
    }

    private function prevIndex()
    {
        $this->index--;
    }

    private function error($msg)
    {
        return 'Error (' . ($this->index - 1) . '): ' . $msg;
    }

    private function expression()
    {
        $a = $this->termOperation($this->term());
        $c = $this->nextChar();
        if ($c == '') {
            return $a;
        }
        if ($c == '+' || $c == '-') {
            $b = $this->expression();
            return $this->operation($a, $c, $b);
        }
        $this->prevIndex();
        return $a;
    }

    private function term()
    {
        $a = $this->factor();
        $c = $this->nextChar();
        if ($c == '') {
            return [$a];
        }
        if ($c == '*' || $c == '/') {
            $b = $this->term();
            array_unshift($b, $a, $c);
            return $b;
        }
        $this->prevIndex();
        return [$a];
    }

    private function termOperation(Array $term)
    {
        $result = $term[0];
        for ($i = 1; $i < count($term); $i += 2) {
            $result = $this->operation($result, $term[$i], $term[$i + 1]);
        }
        return $result;
    }

    private function factor()
    {
        $c = $this->nextChar();
        if ($c == '(') {
            $a = $this->expression();
            $c = $this->nextChar();
            if ($c == ')') {
                return $a;
            }
            throw new Exception($this->error('Se esperaba un cierre de parentesis'));
        } elseif (ctype_digit($c) || $c == '-' || $c == '.') {
            $this->prevIndex();
            $a = $this->number();
            return $a;
        } elseif (ctype_alpha($c)) {
            $this->prevIndex();
            $a = $this->functionExp();
            $c = $this->nextChar();
            if ($c == '(') {
                $b = $this->functionParams();
                $c = $this->nextChar();
                if ($c == ')') {
                    return $this->executeFunction($a, $b);
                }
                throw new Exception($this->error('Se esperaba finalizar los parametros'));
            }
            throw new Exception($this->error('Se esperaba un paretensis para los parametros'));
        } elseif ($c == '<') {
            $a = $this->variable();
            $c = $this->nextChar();
            if ($c == '>') {
                return $a;
            }
            throw new Exception($this->error('Se esperaba un cierre de variable'));
        }
        throw new Exception($this->error('Se esperaba una variable, una expresion o un número'));
    }

    private function functionParams()
    {
        $a = $this->expression();
        $c = $this->nextChar();
        if ($c == '') {
            return [$a];
        }
        if ($c == ',') {
            $b = $this->functionParams();
            array_unshift($b, $a);
            return $b;
        }
        $this->prevIndex();
        return [$a];
    }

    private function variable()
    {
        $var_key = '';
        while (ctype_alnum($c = $this->nextChar(false))) {
            $var_key .= $c;
        }
        if ($var_key == '') {
            throw new Exception($this->error('Se esperaba el nombre de una variable'));
        }
        if ($c != '') {
            $this->prevIndex();
        }
        if (!$this->evaluate) {
            return 0;
        }
        if (!array_key_exists($var_key, $this->variables)) {
            throw new Exception($this->error('La variable <' . $var_key . '> no existe'));
        }
        return $this->variables[$var_key];
    }

    private function number()
    {
        $c = $this->nextChar();
        $dot = false;
        $number = '';
        if ($c == '-' || ctype_digit($c) || $c == '.') {
            if ($c == '.') {
                $dot = true;
            }
        }
        $number .= $c;
        while (ctype_digit($c = $this->nextChar(false)) || $c == '.') {
            if ($c == '.') {
                if ($dot) {
                    throw new Exception($this->error('Doble puntuacion en numero'));
                }
                $dot = true;
            }
            $number .= $c;
        }
        if ($c != '') {
            $this->prevIndex();
        }
        return doubleval($number);
    }

    private function functionExp()
    {
        $function_name = '';
        while (ctype_alpha($c = $this->nextChar(false))) {
            $function_name .= $c;
        }
        if ($c != '') {
            $this->prevIndex();
        }
        if (!array_key_exists((string)$function_name, $this->functions) && $this->evaluate) {
            throw new Exception($this->error('No existe la function ' . $function_name));
        }
        return $function_name;
    }

    private function operation($a, $o, $b)
    {
        if ($this->evaluate) {
            switch ($o) {
                case '+':
                    return $a + $b;
                case '-':
                    return $a - $b;
                case '*':
                    return $a * $b;
                case '/':
                    return $a / $b;
            }
        }
        return 0;
    }

    private function executeFunction($name, $params)
    {
        if ($this->evaluate) {
            $function = new ReflectionFunction($this->functions[$name]);
            $total_params = $function->getNumberOfParameters();
            if ($total_params != count($params)) {
                throw new Exception($this->error('La funcion ' . $name . ' requiere ' . $total_params . ' parametros'));
            }
            return $function->invokeArgs($params);
        }
        return 0;
    }

    public function setFunction($function_key, $function = null)
    {
        if (is_array($function_key)) {
            $this->functions = array_merge($this->functions, $function_key);
        } else {
            $this->functions[$function_key] = $function;
        }
    }
}
/*
$a = new Evaluator();
$a->setFunction('alex', function($a){
    return $a*2*2;
});
$a->setExpression('acos(-1)+alex(sin(1)*2+<var>)');

echo $a->evaluate([
    'var'=>2
]);*/

