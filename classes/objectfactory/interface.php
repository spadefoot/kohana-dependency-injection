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
 * This class provides the contract for an object factory that will read a container
 * with object definitions.
 *
 * @package DI
 * @category Object Factory
 * @version 2011-12-22
 */
interface ObjectFactory_Interface {

    /**
     * This function instantiates an object identified by the specified id.
     *
     * @access public
     * @param string $id                        the object's id
     * @return mixed                            an instance of the class
     */
    public function get_object($id);

    /**
     * This function returns the scope of the object with the specified id.
     *
     * @access public
     * @param string $id                        the object's id
     * @return string                           the scope of the the object with the
     *                                          specified id
     */
    public function get_object_scope($id);

    /**
     * This function returns either the object's type for the specified id or NULL if the object's
     * type cannot be determined.
     *
     * @access public
     * @param string $id                        the object's id
     * @return string                           the object's type
     */
    public function get_object_type($id);

    /**
     * This function determines whether an object with the specified id has been defined
     * in the container.
     *
     * @access public
     * @param string $id                        the object's id
     * @return boolean                          whether an object with the specified id has
     *                                          been defined in the container
     */
    public function has_object($id);

}
?>