<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Copyright 2011 Spadefoot
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *      http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

/**
 * This class handles object creation using an XML based container of object
 * definitions.
 *
 * @package DI
 * @category Object Factory
 * @version 2011-12-22
 *
 * @see http://static.springsource.org/spring/docs/2.5.x/reference/beans.html
 * @see http://www.springframework.net/doc-latest/reference/html/objects.html
 * @see http://msdn.microsoft.com/en-us/magazine/cc163739.aspx
 */
class XMLObjectFactory extends Kohana_Object implements ObjectFactory_Interface {

    /**
     * This variable stores the hashed key that represents the context.
     *
     * @access protected
     * @var string
     */
    protected $context = NULL;

    /**
     * This variable stores the value set for the default-init-method.
     *
     * @access protected
     * @var string
     */
    //protected $default_init_method = NULL;

    /**
     * This variable keeps track of ids to help prevent circular references.
     *
     * @access protected
     * @var array
     */
    protected $ids = NULL;

    /**
     * This variable stores the XML resource with the container.
     *
     * @access protected
     * @var SimpleXMLElement
     */
    protected $resource = NULL;

    /**
     * This variable stores a reference to the session class.
     *
     * @access protected
     * @var Session
     */
    protected $session = NULL;

    /**
     * This variable stores an array of all singleton instance initiated by
     * the factory.
     *
     * @access protected
     * @var array
     */
    protected $singletons = NULL;

    /**
     * This constructor creates an instance of this class with the specified resource.
     *
     * @access public
     * @param SimpleXMLElement $resource        the XML resource with the container
     * @return XmlObjectFactory                 an instance of this class
     */
    public function __construct(SimpleXMLElement $resource) {
        $this->resource = $resource;
        $this->context = md5(serialize($resource->asXML()));
        $this->session = Session::instance();
        $this->singletons = array();
        /*
        $nodes = $resource->xpath("/objects");
        if (!empty($nodes)) {
            $attributes = $nodes[0]->attributes();
            if (isset($attributes['default-init-method'])) {
                $default_init_method = (string)$attributes['default-init-method'];
                if (!preg_match('/^[a-z_][a-z0-9_]*$/i', $default_init_method)) {
                    throw new Kohana_Exception('ParseException', array(':method' => $default_init_method));
                }
                $this->default_init_method = $default_init_method;
            }
        }
        */
    }

    /**
     * This function instantiates an object identified by the specified id.
     *
     * @access public
     * @param string $id                        the object's id
     * @return object                           an instance of the object
     * @throws InstantiationException           indicates that a problem occurred during
     *                                          the instantiation
     */
    public function get_object($id) {
        $this->ids = array();
        $object = $this->get_instance($id);
        return $object;
    }

    /**
     * This function returns the scope of the object with the specified id.
     *
     * @access public
     * @param string $id                        the object's id
     * @return string                           the scope of the the object with the
     *                                          specified id
     * @throws ParseException                   indicates that a problem occurred when
     *                                          parsing the XML file
     */
    public function get_object_scope($id) {
        if (is_string($id) && preg_match('/^[a-z0-9_]+$/i', $id)) {
            $nodes = $this->resource->xpath("/objects/object[@id='{$id}']");
            if (!empty($nodes)) {
                $attributes = $nodes[0]->attributes();
                if (isset($attributes['scope'])) {
                    $scope = strtolower((string)$attributes['scope']);
                    if (!preg_match('/^singleton|prototype|session$/', $scope)) {
                        throw new Kohana_Exception('ParseException', array(':id' => $id, ':scope' => $scope));
                    }
                    return $scope;
                }
                return 'singleton';
            }
            return NULL;
        }
        throw new Kohana_Exception('InvalidArgumentException', array(':id' => $id));
    }

    /**
     * This function returns either the object's type for the specified id or NULL if the object's
     * type cannot be determined.
     *
     * @access public
     * @param string $id                        the object's id
     * @return string                           the object's type
     */
    public function get_object_type($id) {
        if (is_string($id) && preg_match('/^[a-z0-9_]+$/i', $id)) {
            $nodes = $this->resource->xpath("/objects/object[@id='{$id}']");
            if (!empty($nodes)) {
                $attributes = $nodes[0]->attributes();
                if (isset($attributes['type'])) {
                    $type = (string)$attributes['type'];
                    if (preg_match('/^[a-z_][a-z0-9_]*$/i', $type)) {
                        return $type;
                    }
                }
            }
            return NULL;
        }
        throw new Kohana_Exception('InvalidArgumentException', array(':id' => $id));
    }

    /**
     * This function determines whether an object with the specified id has been defined
     * in the container.
     *
     * @access public
     * @param string $id                        the object's id
     * @return boolean                          whether an object with the specified id has
     *                                          been defined in the container
     */
    public function has_object($id) {
        if (is_string($id) && preg_match('/^[a-z0-9_]+$/i', $id)) {
            $nodes = $this->resource->xpath("/objects/object[@id='{$id}']");
            if (!empty($nodes)) {
                return TRUE;
            }
            return FALSE;
        }
        throw new Kohana_Exception('InvalidArgumentException', array(':id' => $id));
    }

    ///////////////////////////////////////////////////////////////HELPERS//////////////////////////////////////////////////////////////

    /**
     * This function fetches an array of all constructor arguments for the specified id.
     *
     * @access protected
     * @param string $id                        the object's id
     * @return array                            an array of all constructor arguments for the
     *                                          specified id
     * @throws InstantiationException           indicates that problem occurred during
     *                                          the instantiation
     */
    protected function get_constructor_args($id) {
        $constructor_args = array();
        $nodes = $this->resource->xpath("/objects/object[@id='{$id}']/constructor-arg");
        foreach ($nodes as $node) {
    	    $attributes = $node->attributes();
            $children = $node->children();
		    if (count($children) > 0) {
		        foreach ($children as $child) {
		            switch (strtolower($child->getName())) {
    	                case 'idref':
    	                    $constructor_args[] = $this->get_idref($id, $child);
    	                break;
		                case 'list':
		                    $constructor_args[] = $this->get_list($id, $child);
		                break;
		                case 'map':
		                    $constructor_args[] = $this->get_map($id, $child);
		                break;
                        case 'null':
                            $constructor_args[] = $this->get_null($id, $child);
                        break;
		                case 'ref':
		                    $constructor_args[] = $this->get_ref($id, $child);
		                break;
		                case 'value':
		                    $constructor_args[] = $this->get_value($id, $child);
		                break;
		                default:
		                    throw new Kohana_Exception('InstantiationException', array(':id' => $id, ':child' => $child));
		                break;
		            }
		        }
		    }
            else if (isset($attributes['ref'])) {
    	        $constructor_args[] = $this->get_instance((string)$attributes['ref']);
    	    }
    	    else if (isset($attributes['value'])) {
    	        $value = (string)$attributes['value'];
    	        if (isset($attributes['type'])) {
    	            $type = (string)$attributes['type'];
    	            if (!preg_match('/^(bool(ean)?|int(eger)?|float|string|null)$/i', $type)) {
                        throw new Kohana_Exception('InstantiationException', array(':id' => $id, ':type' => $type));
                    }
    	            settype($value, $type);
                }
    	        $constructor_args[] = $value;
    	    }
    	    else {
    	        throw new Kohana_Exception('InstantiationException', array(':id' => $id, ':attributes' => $attributes));
    	    }
    	}
		return $constructor_args;
    }

    /**
     * This function processes an "entry" node.
     *
     * @access protected
     * @param string $id                        the object's id
     * @param object &$node                     a reference to the "entry" node
     * @return array                            a key/value map
     * @throws InstantiationException           indicates that problem occurred during
     *                                          the instantiation
     */
    protected function get_entry($id, &$node) {
        $entry = array();
        $attributes = $node->attributes();
	    if (!isset($attributes['key'])) {
            throw new Kohana_Exception('InstantiationException', array(':id' => $id, ':attributes' => $attributes));
        }
	    $key = (string)$attributes['key'];
	    if (!preg_match('/^[a-z_][a-z0-9_]*$/i', $key)) {
	        throw new Kohana_Exception('InstantiationException', array(':id' => $id, ':attributes' => $attributes));
	    }
	    $children = $node->children();
	    if (count($children) > 0) {
	        foreach ($children as $child) {
	            switch (strtolower($child->getName())) {
	                case 'idref':
	                    $entry[$key] = $this->get_idref($id, $child);
	                break;
	                case 'list':
	                    $entry[$key] = $this->get_list($id, $child);
	                break;
	                case 'map':
	                    $entry[$key] = $this->get_map($id, $child);
	                break;
                    case 'null':
                        $entry[$key] = $this->get_null($id, $child);
                    break;
	                case 'ref':
	                    $entry[$key] = $this->get_ref($id, $child);
	                break;
	                case 'value':
	                    $entry[$key] = $this->get_value($id, $child);
	                break;
	                default:
	                    throw new Kohana_Exception('InstantiationException', array(':id' => $id, ':child' => $child));
	                break;
	            }
	        }
	    }
	    else if (isset($attributes['value-ref'])) {
	        $entry[$key] = $this->get_instance((string)$attributes['value-ref']);
	    }
	    else if (isset($attributes['value'])) {
	        $value = (string)$attributes['value'];
	        if (isset($attributes['type'])) {
	            $type = (string)$attributes['type'];
	            if (!preg_match('/^(bool(ean)?|int(eger)?|float|string|null)$/i', $type)) {
                    throw new Kohana_Exception('InstantiationException', array(':id' => $id, ':type' => $type));
                }
	            settype($value, $type);
            }
            $entry[$key] = $value;
	    }
	    else {
	        throw new Kohana_Exception('InstantiationException', array(':id' => $id, ':attributes' => $attributes));
	    }
	    return $entry;
    }

    /**
     * This function processes an "idref" node.
     *
     * @access protected
     * @param string $id                        the object's id
     * @param object &$node                     a reference to the "idref" node
     * @return string                           the id reference
     * @throws InstantiationException           indicates that problem occurred during
     *                                          the instantiation
     */
    protected function get_idref($id, &$node) {
        $attributes = $node->attributes();
	    if (!isset($attributes['object'])) {
            throw new Kohana_Exception('InstantiationException', array(':id' => $id, ':node' => $node));
        }
        $idref = (string)$attributes['object'];
        if (!$this->has_object($idref)) {
            throw new Kohana_Exception('InstantiationException', array(':id' => $id, ':idref' => $idref));
        }
        return $idref;
    }

    /**
     * This function uses the specified id to return an instance of the object.  Having
     * this function helps detect circular references.
     *
     * @access public
     * @param string $id                        the object's id
     * @return object                           an instance of the object
     * @throws InstantiationException           indicates that problem occurred during
     *                                          the instantiation
     */
    protected function get_instance($id) {
        if (is_string($id) && preg_match('/^[a-z0-9_]+$/i', $id)) {            
            $session_key = __CLASS__ . '::' . $this->context . '::' . $id;
            $object = $this->session->get($session_key, NULL);
            if (!is_null($object)) {
                return $object;
            }
            else if (isset($this->singletons[$id])) {
			    return $this->singletons[$id];
		    }
            $nodes = $this->resource->xpath("/objects/object[@id='{$id}']");
            if (!empty($nodes)) {
                if (isset($this->ids[$id])) { // checks for circular references
                    throw new Kohana_Exception('InstantiationException', array(':id' => $id, '#ids' => $this->ids));
                }
                $this->ids[$id] = count($this->ids); // stack level
                $attributes = $nodes[0]->attributes();
                if (isset($attributes['factory-object']) && isset($attributes['factory-method'])) {
                    $factory_object = $this->get_instance((string)$attributes['factory-object']);
                    $class = new ReflectionClass($factory_object);
                    $factory_method = (string)$attributes['factory-method'];
		            if (preg_match('/^[a-z_][a-z0-9_]*$/i', $factory_method) && $class->hasMethod($factory_method)) {
        				$method = $class->getMethod($factory_method);
        				if (!$method->isPublic() || $method->isStatic() || $method->isAbstract() || $method->isDestructor()) {
                	        throw new Kohana_Exception('InstantiationException', array(':id' => $id, ':method' => $factory_method));
                	    }
                	    $constructor_args = $this->get_constructor_args($id);
                	    $object = $method->invokeArgs($factory_object, $constructor_args);
        			}
        			else {
        			    throw new Kohana_Exception('InstantiationException', array(':id' => $id, ':method' => $factory_method));
        			}
                }
                else if (isset($attributes['type'])) {
                    $type = (string)$attributes['type'];
                    if (!preg_match('/^[a-z_][a-z0-9_]*$/i', $type)) {
                        throw new Kohana_Exception('InstantiationException', array(':id' => $id, ':type' => $type));
                    }
                    $class = new ReflectionClass($type);
                    if ($class->isAbstract()) {
        				throw new Kohana_Exception('InstantiationException', array(':id' => $id, ':type' => $type));
        			}
                    $constructor_args = $this->get_constructor_args($id);
                    if (isset($attributes['factory-method'])) {
                        $factory_method = (string)$attributes['factory-method'];
    		            if (preg_match('/^[a-z_][a-z0-9_]*$/i', $factory_method) && $class->hasMethod($factory_method)) {
            				$method = $class->getMethod($factory_method);
            				if (!$method->isPublic() || !$method->isStatic()) {
                    	        throw new Kohana_Exception('InstantiationException', array(':id' => $id, ':method' => $factory_method));
                    	    }
                    	    $object = $method->invokeArgs(NULL, $constructor_args);
            			}
            			else {
            			    throw new Kohana_Exception('InstantiationException', array(':id' => $id, ':method' => $factory_method));
            			}
                    }
                    else {
                        $object = $class->newInstanceArgs($constructor_args);
                    }
                }
                else {
                    throw new Kohana_Exception('InstantiationException', array(':id' => $id, ':attributes' => $attributes));
                }
    		    $this->set_properties($id, $object);
    		    if (isset($attributes['init-method'])) {
    		        $init_method = (string)$attributes['init-method'];
    		        if (preg_match('/^[a-z_][a-z0-9_]*$/i', $init_method) && $class->hasMethod($init_method)) {
            			$method = $class->getMethod($init_method);
            			if (!$method->isPublic() || $method->isStatic() || $method->isAbstract() || $method->isDestructor()) {
                	        throw new Kohana_Exception('InstantiationException', array(':id' => $id, ':method' => $init_method));
                	    }
                	    $method->invoke($object);
            		}
            		else {
            		    throw new Kohana_Exception('InstantiationException', array(':id' => $id, ':method' => $init_method));
            		}
    		    }
    		    unset($this->ids[$id]);
                if (isset($attributes['scope'])) {
                    $scope = (string)$attributes['scope'];
                    switch (strtolower($scope)) {
                        case 'session':
                            $this->session->set($session_key, $object);
                            return $object;
                        case 'singleton':
                            $this->singletons[$id] = &$object;
                            return $object;
                        case 'prototype':
                            return $object;
                        default:
                            throw new Kohana_Exception('InstantiationException', array(':id' => $id, ':scope' => $scope));
                    }
        		}
        		else {
        		    $this->singletons[$id] = &$object;
                    return $object;
        		}
            }
            return NULL;
        }
        throw new Kohana_Exception('InstantiationException', array(':id' => $id));
    }

    /**
     * This function processes a "list" node.
     *
     * @access protected
     * @param string $id                        the object's id
     * @param object &$node                     a reference to the "list" node
     * @return array                            a list of values
     * @throws InstantiationException           indicates that problem occurred during
     *                                          the instantiation
     */
    protected function get_list($id, &$node) {
        $list = array();
        $children = $node->children();
        foreach ($children as $child) {
            switch (strtolower($child->getName())) {
                case 'idref':
                    $list[] = $this->get_idref($id, $child);
                break;
                case 'list':
                    $list[] = $this->get_list($id, $child);
                break;
                case 'map':
                    $list[] = $this->get_map($id, $child);
                break;
                case 'null':
                    $list[] = $this->get_null($id, $child);
                break;
                case 'ref':
                    $list[] = $this->get_ref($id, $child);
                break;
                case 'value':
                    $list[] = $this->get_value($id, $child);
                break;
                default:
                    throw new Kohana_Exception('InstantiationException', array(':id' => $id, ':child' => $child));
                break;
            }
        }
        return $list;
    }

    /**
     * This function processes a "map" node.
     *
     * @access protected
     * @param string $id                        the object's id
     * @param object &$node                     a reference to the "map" node
     * @return array                            a key/value map
     * @throws InstantiationException           indicates that problem occurred during
     *                                          the instantiation
     */
    protected function get_map($id, &$node) {
        $map = array();
        $children = $node->children();
        foreach ($children as $child) {
            switch (strtolower($child->getName())) {
                case 'entry':
                    $map = array_merge($this->get_entry($id, $child), $map);
                break;
                default:
                    throw new Kohana_Exception('InstantiationException', array(':id' => $id, ':child' => $child));
                break;
            }
        }
        return $map;
    }

    /**
     * This function processes a "null" node.
     *
     * @access protected
     * @param string $id                        the object's id
     * @param object &$node                     a reference to the "null" node
     * @return NULL                             a NULL value
     */
    protected function get_null($id, &$node) {
        return NULL;
    }

    /**
     * This function processes a "ref" node.
     *
     * @access protected
     * @param string $id                        the object's id
     * @param object &$node                     a reference to the "ref" node
     * @return object                           an instance of the object
     * @throws InstantiationException           indicates that problem occurred during
     *                                          the instantiation
     */
    protected function get_ref($id, &$node) {
        $attributes = $node->attributes();
	    if (!isset($attributes['object'])) {
            throw new Kohana_Exception('InstantiationException', array(':id' => $id, ':node' => $node));
        }
        $object = $this->get_instance((string)$attributes['object']);
        return $object;
    }

    /**
     * This function processes a "value" node.
     *
     * @access protected
     * @param string $id                        the object's id
     * @param object &$node                     a reference to the "value" node
     * @return mixed                            the value
     * @throws InstantiationException           indicates that problem occurred during
     *                                          the instantiation
     */
    protected function get_value($id, &$node) {
        $children = $node->children();
        if (count($children) > 0) {
            $value = '';
            foreach ($children as $child) {
                switch (strtolower($child->getName())) {
                    case 'null':
                        $value = $this->get_null($id, $child);
                    break;
                    default:
                        throw new Kohana_Exception('InstantiationException', array(':id' => $id, ':child' => $child));
                    break;
                }
            }
            return $value;
        }
        else {
            $attributes = $node->attributes();
            $value = (string)$node[0];
            if (isset($attributes['type'])) {
                $type = (string)$attributes['type'];
                if (!preg_match('/^(bool(ean)?|int(eger)?|float|string|null)$/i', $type)) {
                    throw new Kohana_Exception('InstantiationException', array(':id' => $id, ':type' => $type));
                }
                settype($value, $type);
            }
            if (is_string($value)) {
                $attributes = $node->attributes('xml', TRUE);
                if (isset($attributes['space'])) {
                    $space = (string)$attributes['space'];
                    if (!preg_match('/^preserve$/i', $space)) {
                        throw new Kohana_Exception('InstantiationException', array(':id' => $id, ':space' => $space));
                    }
                }
                else {
                    $value = trim($value);
                }
            }
            return $value;
        }
    }

    /**
     * This function assigns any property values to the specified object.
     *
     * @access protected
     * @param string $id                        the object's id
     * @param mixed &$object                    a reference to the object
     * @throws InstantiationException           indicates that problem occurred during
     *                                          the instantiation
     */
    protected function set_properties($id, &$object) {
        $class = new ReflectionClass($object);
		$nodes = $this->resource->xpath("/objects/object[@id='{$id}']/property");
	    foreach ($nodes as $node) {
		    $attributes = $node->attributes();
		    if (!isset($attributes['name'])) {
	            throw new Kohana_Exception('InstantiationException', array(':id' => $id, ':attributes' => $attributes));
	        }
		    $name = (string)$attributes['name'];
		    if (!preg_match('/^[a-z_][a-z0-9_]*$/i', $name)) {
		        throw new Kohana_Exception('InstantiationException', array(':id' => $id, ':attributes' => $attributes));
		    }
			$value = NULL;
		    $children = $node->children();
		    if (count($children) > 0) {
		        foreach ($children as $child) {
		            switch (strtolower($child->getName())) {
		                case 'idref':
		                    $value = $this->get_idref($id, $child);
		                break;
		                case 'list':
		                    $value = $this->get_list($id, $child);
		                break;
		                case 'map':
		                    $value = $this->get_map($id, $child);
		                break;
                        case 'null':
                            $value = $this->get_null($id, $child);
                        break;
		                case 'ref':
		                    $value = $this->get_ref($id, $child);
		                break;
		                case 'value':
		                    $value = $this->get_value($id, $child);
		                break;
		                default:
		                    throw new Kohana_Exception('InstantiationException', array(':id' => $id, ':child' => $child));
		                break;
		            }
		        }
		    }
		    else if (isset($attributes['expression'])) {
		        $expression = (string)$attributes['expression'];
		        @eval('$value = ' . $expression . ';');
		    }
		    else if (isset($attributes['ref'])) {
    	        $value = $this->get_instance((string)$attributes['ref']);
    	    }
    	    else if (isset($attributes['value'])) {
    	        $value = (string)$attributes['value'];
    	        if (isset($attributes['type'])) {
    	            $type = (string)$attributes['type'];
    	            if (!preg_match('/^(bool(ean)?|int(eger)?|float|string|null)$/i', $type)) {
                        throw new Kohana_Exception('InstantiationException', array(':id' => $id, ':type' => $type));
                    }
    	            settype($value, $type);
                }
    	    }
    	    else {
    	        throw new Kohana_Exception('InstantiationException', array(':id' => $id, ':attributes' => $attributes));
    	    }
			if ($class->hasProperty($name)) {
				$property = $class->getProperty($name);
				if (!$property->isPublic()) {
				    throw new Kohana_Exception('InstantiationException', array(':id' => $id, ':name' => $name));
			    }
				$property->setValue($object, $value);
			}
			/*
			else if ($class->hasMethod($name)) {
				$method = $class->getMethod($name);
				if ($method->isAbstract() || $method->isDestructor() || !$method->isPublic()) {
        	        throw new Kohana_Exception('InstantiationException', array(':id' => $id, ':name' => $name));
        	    }
        	    $method->invoke($object, $value);
			}
			*/
			else {
    	        throw new Kohana_Exception('InstantiationException', array(':id' => $id, ':name' => $name));
    	    }
		}
    }

}
?>