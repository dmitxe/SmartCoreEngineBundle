<?php 

namespace SmartCore\Bundle\EngineBundle\Engine;

use SmartCore\Bundle\EngineBundle\Controller\Controller;

class Folder extends Controller
{
    protected $_sql_is_active = ' AND is_active = 1 ';
    
    protected $_folder_tree_list_arr = array();
    protected $_folder_tree = array();
    protected $_tree_link = array();
    protected $_tree_level = 0;

    /**
     * Получить данные о папке по её ID.
     *
     * @param int $folder_id
     * @param string $language - указать извлекаемый язык (пока не испольузется.)
     * @return object
     */
    public function getDataById($folder_id, $language = false)
    {
        $sql = "SELECT *
            FROM {$this->DB->prefix()}engine_folders
            WHERE site_id = '{$this->engine('site')->getId()}'
            {$this->_sql_is_active}
            AND is_deleted = 0
            AND folder_id = '{$folder_id}' ";

        return $this->DB->fetchObject($sql);
    }

    /**
     * Получить данные о папке.
     *
     * @param string $uri_part - запрашиваемый чать УРИ
     * @param int $pid - искать в родительском ID.
     * @param string $language - указать извлекаемый язык (пока не испольузется.)
     * @return object|false
     */
    public function getData($uri_part, $pid, $language = false)
    {
        $sql = "SELECT *
            FROM {$this->DB->prefix()}engine_folders
            WHERE site_id = '{$this->engine('site')->getId()}'
            {$this->_sql_is_active}
            AND is_deleted = 0
            AND uri_part = '{$uri_part}'
            AND pid = '{$pid}' ";

        return $this->DB->fetchObject($sql);
    }

    /**
     * Обновление данных о папке.
     *
     * @param int $folder_id
     * @param array $pd
     * @return bool
     */
    public function __update($folder_id, $pd) // @todo 
    {
        $is_file        = is_numeric($pd['is_file']) ? $pd['is_file'] : 0;
        $transmit_nodes = is_numeric($pd['transmit_nodes']) ? $pd['transmit_nodes'] : 0;
        $parser_node_id = (is_numeric($pd['parser_node_id']) and $pd['parser_node_id'] != 0) ? $pd['parser_node_id'] : 'NULL';
        $pos            = is_numeric($pd['pos']) ? $pd['pos'] : 0;
        $permissions    = strlen(trim($pd['permissions'])) == 0 ? 'NULL' : $this->DB->quote(trim($pd['permissions']));
        $layout         = strlen(trim($pd['layout'])) == 0 ? 'NULL' : $this->DB->quote(trim($pd['layout']));
        
        if ($folder_id == 1) {
            $is_active = 1;
        } else if (is_numeric($pd['is_active'])) {
            $is_active = $pd['is_active'];
        } else {
            $is_active = 1;
        }

        if ($folder_id == 1) {
            $pid = 0;
        } else if (is_numeric($pd['pid'])) {
            $pid = $pd['pid'];
        } else {
            return false;
        }

        $tmp = array();
        if (!empty($pd['nodes_blocks']['single'])) {
            $tmp['single'] = trim($pd['nodes_blocks']['single']);
        }
        if (!empty($pd['nodes_blocks']['inherit'])) {
            $tmp['inherit'] = trim($pd['nodes_blocks']['inherit']);
        }
        if (!empty($pd['nodes_blocks']['except'])) {
            $tmp['except'] = trim($pd['nodes_blocks']['except']);
        }

        $nodes_blocks = empty($tmp) ? 'NULL' : $this->DB->quote(serialize($tmp));

        $title = $this->DB->quote(trim($pd['title']));
        $descr = $this->DB->quote(trim($pd['descr']));
        $redirect_to = $this->DB->quote(trim($pd['redirect_to']));

        $Helper_Uri = new Helper_Uri();
        $uri_part = $Helper_Uri->preparePart($pd['uri_part']);

        // Проверка на существование папки.
        $folder = $this->getData($uri_part, $pid);
        
        $uri_part = ($folder !== false and $folder->folder_id != $folder_id) ? $folder_id : $this->DB->quote($uri_part);
        
        $sql = "
            UPDATE {$this->DB->prefix()}engine_folders SET
                uri_part = $uri_part,
                redirect_to = $redirect_to,
                parser_node_id = $parser_node_id,
                is_active = '$is_active',
                pid = '$pid',
                pos = '$pos',
                is_file = '$is_file',
                transmit_nodes = '$transmit_nodes',
                permissions = $permissions,
                nodes_blocks = $nodes_blocks,
                title = $title,
                descr = $descr,
                layout = $layout
            WHERE
                folder_id = '$folder_id'
            AND site_id = '{$this->engine('site')->getId()}' ";
        $this->DB->exec($sql);
        
        /*
        $sql = "
            UPDATE {$this->DB->prefix()}engine_folders_translation SET
                title = $title,
                descr = $descr
            WHERE folder_id = '$folder_id'
            AND language_id = '{$this->engine('env')->language_id}'
            AND site_id = '{$this->Request->Env->site_id}' ";
        $this->DB->exec($sql);
        */
        $this->Cache->updateFolder($folder_id);
        return true;
    }
    
    /**
     * Создание новой папки.
     *
     * @param array $pd
     * @return false|int - id созданной папки.
     */
    public function __create($pd) // @todo 
    {
        $is_active        = is_numeric($pd['is_active']) ? $pd['is_active'] : 1;
        $is_file        = is_numeric($pd['is_file']) ? $pd['is_file'] : 0;
        $transmit_nodes    = is_numeric($pd['transmit_nodes']) ? $pd['transmit_nodes'] : 0;
        $parser_node_id    = (is_numeric($pd['parser_node_id']) and $pd['parser_node_id'] != 0) ? $pd['parser_node_id'] : 'NULL';
        $pos            = is_numeric($pd['pos']) ? $pd['pos'] : 0;
        $permissions     = strlen(trim($pd['permissions'])) == 0 ? 'NULL' : $this->DB->quote(trim($pd['permissions']));
        $layout         = strlen(trim($pd['layout'])) == 0 ? 'NULL' : $this->DB->quote(trim($pd['layout']));
        
        if (is_numeric($pd['pid'])) {
            $pid = $pd['pid'];
        } else {
            return false;
        }

        // Своеобразный автоинкремент
        $sql = "SELECT max(folder_id) AS folder_id
            FROM {$this->DB->prefix()}engine_folders
            WHERE site_id = '{$this->engine('site')->getId()}' ";
        $folder_id = $this->DB->fetchObject($sql)->folder_id + 1;

        $title = strlen(trim($pd['title'])) == 0 ? $title = "'Новая папка $folder_id'" : $this->DB->quote(trim($pd['title']));
        
        $descr = $this->DB->quote(trim($pd['descr']));
        $redirect_to = $this->DB->quote(trim($pd['redirect_to']));

        // Подготовка части УРИ.
        $Helper_Uri = new Helper_Uri();
        $uri_part = $Helper_Uri->preparePart($pd['uri_part']);

        // Проверка на существование папки.
        $uri_part = $this->getData($uri_part, $pid) !== false ? $folder_id : $this->DB->quote($uri_part);

        if (strlen(trim($pd['uri_part'])) == 0) {
            $uri_part = "'NULL'"; // @todo некрасиво, надо переделать.
        }
        
        $sql = "
            INSERT INTO {$this->DB->prefix()}engine_folders
                (folder_id,    pid, site_id, 
                 pos, uri_part, is_active, 
                 redirect_to, parser_node_id, transmit_nodes, 
                 is_file, permissions, create_datetime,
                 owner_id, title, descr, layout)
            VALUES
                ('$folder_id', '$pid', '{$this->engine('site')->getId()}',
                 '$pos', $uri_part, '$is_active',
                  $redirect_to, $parser_node_id, '$transmit_nodes',
                 '$is_file', $permissions, NOW(),
                 '{$this->User->getId()}', $title, $descr, $layout) ";
        $result = $this->DB->query($sql);
        
        // Если $uri_part не указан или указан неверно, то ставится равным ИД новой папки.
        if ($uri_part == "'NULL'") {
            $sql = "
                UPDATE {$this->DB->prefix()}engine_folders SET
                    uri_part = '$folder_id'
                WHERE
                    folder_id = '$folder_id'
                AND site_id = '{$this->engine('site')->getId()}' ";
            $this->DB->exec($sql);
        }
        
        /*
        $sql = "
            INSERT INTO {$this->DB->prefix()}engine_folders_translation 
                (folder_id, site_id, language_id, title, descr)
            VALUES 
                ('$folder_id', '{$this->engine('site')->getId()}', '{$this->engine('env')->language_id}', $title, $descr) ";
        $this->DB->exec($sql);
        */
        
        return $is_active ? $folder_id : $pid;
    }
    
    /**
     * Получить плоский список папок. Уровень вложенности указывается значением 'level'.
     *
     * @param
     * @return array
     */
    public function getList()
    {
        $this->buildTree(0, 0);
        return $this->getTreeList();
    }

    /**
     * Получение "плоского списка" папок вида:
     * 
     * [1] => Array
     *   (
     *     [title] => Главная
     *     [link] => /
     *     [level] => 0
     *   )
     *
     * @return array
     */
    public function getTreeList()
    {
        if (count($this->_folder_tree_list_arr) == 0) {
            $this->_getTreeList($this->_folder_tree);
        }
        
        return $this->_folder_tree_list_arr;
    }
    
    /**
     * Вспомогательный метод.
     * 
     * @param array $data
     */
    private function _getTreeList($data)
    {
        foreach ($data as $key => $value) {
            $this->_folder_tree_list_arr[$value['folder_id']] = array(
                'title'        => $value['title'],
                'link'        => $value['link'],
                'is_active'    => $value['is_active'],
                'pos'        => $value['pos'],
                'level'        => $this->_tree_level,
                );
            
            if (count($value['folders']) > 0) {
                $this->_tree_level++;
                $this->_getTreeList($value['folders']);
            }
        }
        
        $this->_tree_level--;
    }
        
    /**
     * Построение дерева папок.
     * 
     * @param int $parent_id
     * @param int $max_depth - максимальная вложенность
     */
    public function buildTree($parent_id, $max_depth = false, &$tree = false)
    { 
        $sql = "SELECT *
            FROM {$this->DB->prefix()}engine_folders
            WHERE site_id = '{$this->engine('site')->getId()}'
            {$this->_sql_is_active}
            AND is_deleted = 0
            AND pid = '{$parent_id}'
            ORDER BY pos ";
        $result = $this->DB->query($sql);
        if ($result->rowCount() > 0) {
            $this->_tree_level++;
            
            while ($row = $result->fetchObject()) {
                if ($parent_id > 0) {
                    $this->_tree_link[$this->_tree_level] = $row->uri_part;
                }
                
                $uri = $this->engine('env')->get('base_url');
                foreach ($this->_tree_link as $value) {
                    $uri .= $value . '/';
                }
                
                if ($max_depth != false and $max_depth < $this->_tree_level) { // копаем до указанной глубины.
                    continue;
                }

                $tree[$row->folder_id] = array(
                    'folder_id' => $row->folder_id,
                    'is_active' => $row->is_active,
                    'pid'       => $row->pid,
                    'pos'       => $row->pos,
                    'link'      => $uri,
                    'title'     => $row->title,
                    'folders'   => array(),
                    );

                if ($parent_id == 0) {
                    $this->_folder_tree = &$tree;
                }

                $this->buildTree($row->folder_id, $max_depth, $tree[$row->folder_id]['folders']);
            }
            unset($this->_tree_link[$this->_tree_level]);
            $this->_tree_level--;
        }
    }

    /**
     * Получить массив для применения в Helper_Form для элемента select multiOptions
     * 
     * @param int $disable_folder - ID папки, которая будет помечена деактивированная.
     * @return array
     */
    public function __getSelectOptionsArray($disable_folder = false)
    {
        if (count($this->_folder_tree_list_arr) == 0) {
            if (empty($this->_folder_tree)) {
                $this->buildTree(0);
            }
            
            $this->_getTreeList($this->_folder_tree);
        }

        $multi_options = array();
        foreach ($this->_folder_tree_list_arr as $folder_id => $value) {
            $level = '';
            while ($value['level']--) {
                $level .= '.. ';
            }
            
            $multi_options[$folder_id] = array(
                'title' => $level . $value['title'],
                'disabled' => ($disable_folder !== false and $disable_folder == $folder_id) ? true : false,
            );
        }
        
        return $multi_options;
    }

    /**
     * Обновление мета-тэгов.
     *
     * @param int $folder_id - id папки.
     * @param array $pd - массив данных.
     * @return bool
     */
    public function __updateMeta($folder_id, $pd)
    {
        $meta = array();
        foreach ($pd as $key => $value) {
            if (isset($value['delete']) and (string) $value['delete'] === '0') {
                continue;
            }
            $meta[$key] = $value;
        }
        
        $meta = count($meta) == 0 ? 'NULL' : $this->DB->quote(serialize($meta));
        
        $this->DB->exec("
            UPDATE {$this->DB->prefix()}engine_folders SET
                meta = $meta
            WHERE site_id = '{$this->engine('site')->getId()}'
            AND folder_id = '$folder_id' "
        );
        
        return true;
    }

    /**
     * Создание мета-тэга.
     *
     * @param int $folder_id - id папки.
     * @param array $pd - массив данных.
     * @return bool
     */
    public function __createMeta($folder_id, $pd)
    {
        $sql = "SELECT meta 
            FROM {$this->DB->prefix()}engine_folders
            WHERE site_id = '{$this->engine('site')->getId()}'
            AND folder_id = '$folder_id' ";
        $row = $this->DB->fetchObject($sql);
        
        $meta = (empty($row) or empty($row->meta)) ? false : unserialize($row->meta);
        
        // Проверка на существующий тэг.
        if (isset($meta[strtolower($pd['name'])])) {
            // @todo вывод в систему сообщений об ошибках.
            // echo "Такой тэг уже существует";
            return false;
        } else {
            $meta[strtolower($pd['name'])] = $pd['content'];
            $meta = $this->DB->quote(serialize($meta));
            $sql = "
                UPDATE {$this->DB->prefix()}engine_folders SET
                    meta = $meta
                WHERE site_id = '{$this->engine('site')->getId()}'
                AND folder_id = '$folder_id' ";
            $this->DB->exec($sql);
        }
        
        return true;
    }    
    
    /**
     * Получение полной ссылки на папку, указав её id. Если не указать ид папки, то вернётся текущий путь.
     * 
     * @param int $folder_id
     * @return string $uri
     */
    public function getUri($folder_id = false)
    {
        if ($folder_id === false) {
            $folder_id = $this->engine('env')->get('current_folder_id');
        }

        $uri_parts = array();
        $uri = '';
        
        while($folder_id != 1) {
            $folder = $this->getDataById($folder_id);
            if ($folder !== false) {
                $folder_id = $folder->pid;
                $uri_parts[] = $folder->uri_part;
            } else{
                break;
            }
        }

        $uri_parts = array_reverse($uri_parts);
        foreach ($uri_parts as $value) {
            $uri .= $value . '/';
        }
    
        return $this->engine('env')->get('base_url') . $uri;
    }
    
    /**
     * Роутинг.
     * 
     * @param string $slug
     * @return array
     */
    public function router($slug)
    {
        $data = array(
            'folders' => array(),
            'meta' => array(),
            'status' => 200,
            'template' => 'index',
        );
        
        // @todo при обращении к фронт-контроллеру /web/app.php не коррекнтно определяется активные пункты меню.
        $current_folder_path = $this->engine('env')->get('base_path');
        $router_node_id = null;
        $folder_pid = 0;

        $path_parts = explode('/', $slug);
        
        foreach ($path_parts as $key => $segment) {
            // Проверка строки запроса на допустимые символы.
            // @todo сделать проверку на разрешение круглых скобок.
            if (!empty($segment) and !preg_match('/^[a-z_@0-9.-]*$/iu', $segment)) {
                $data['status'] = 404;
                break;
            }

            // заканчиваем работу, если имя папки пустое и папка не является корневой 
            // т.е. обрабатываем последнюю запись в строке УРИ
            if('' == $segment and 0 != $key) { 
                // @todo видимо здесь надо делать обработчик "файла" т.е. папки с выставленным флагом "is_file".
                break;
            }

            // В данной папке есть нода которой передаётся дальнейший парсинг URI.
            if ($router_node_id !== null) {
                // выполняется часть URI парсером модуля и возвращается результат работы, в дальнейшем он будет передан самой ноде.
                $ModuleRouter = $this->forward($router_node_id . '::router', array(
                    'slug' => str_replace($current_folder_path, '', substr($this->engine('env')->base_path, 0, -1) . $slug))
                );
                
                // Роутер модуля вернул положительный ответ.
                if ($ModuleRouter->isOk()) {
                    $data['folders'][$folder->folder_id]['router_response'] = $ModuleRouter;
                    $data['folders'][$folder->folder_id]['router_node_id'] = $router_node_id;
                    // В случае успешного завершения роутера модуля, роутинг ядром прекращается.
                    break; 
                }
                
                unset($ModuleRouter);
            } // __end if ($router_node_id !== null)

            $folder = $this->getData($segment, $folder_pid);
            
            if ($folder !== false) {
                //if ($this->Permissions->isAllowed('folder', 'read', $folder->permissions)) {
                if ( true ) {
                    // Заполнение мета-тегов.
                    if (!empty($folder->meta)) {
                        foreach (unserialize($folder->meta) as $key2 => $value2) {
                            $data['meta'][$key2] = $value2;
                        }
                    }

                    if ($folder->uri_part !== '') {
                        $current_folder_path .= $folder->uri_part . '/';
                    }

                    // Чтение макета для папки.
                    // @todo возможно ненадо. оставить только один view.
                    if (!empty($folder->layout)) {
                        $data['template'] = $folder->layout;
                    }
                    
                    $folder_pid = $folder->folder_id;
                    $router_node_id = $folder->router_node_id;
                    $data['folders'][$folder->folder_id] = array(
                        'uri' => $current_folder_path,
                        'title' => $folder->title,
                        'descr' => $folder->descr,
                        'is_inherit_nodes' => $folder->is_inherit_nodes,
                        'lockout_nodes' => unserialize($folder->lockout_nodes),
                    );
                    $this->engine('env')->set('current_folder_id', $folder->folder_id);
                    $this->engine('env')->set('current_folder_path', $current_folder_path);
                } else {
                    $data['status'] = 403;
                }
            } else {
                $data['status'] = 404;
            }
        }

        return $data;
    }
}