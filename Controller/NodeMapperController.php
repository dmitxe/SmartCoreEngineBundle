<?php

namespace SmartCore\Bundle\EngineBundle\Controller;

use Symfony\Component\HttpFoundation\Response;
use SmartCore\Bundle\EngineBundle\Engine\Theme;
use SmartCore\Bundle\EngineBundle\Templater\View;
use SmartCore\Bundle\EngineBundle\Container;

class NodeMapperController extends Controller
{
    public function indexAction($slug)
    {
//        sc_dump($user = $this->container->get('security.context')->getToken()->getUser());
//        sc_dump($this->container->getParameterBag());
//        sc_dump($this->container->getParameter('security.role_hierarchy.roles'));

        /*
        if ($this->get('security.context')->isGranted('IS_AUTHENTICATED_ANONYMOUSLY')) {
            echo "123<br />";
        }
        */
        
        //$em = $this->getDoctrine()->getEntityManager();
        //$item = $this->getDoctrine()->getRepository('SmartCoreTexterModule:Item')->find(1);

        // @todo вынести router в другое место... можно сделать в виде отдельного сервиса, например 'engine.folder_router'.
        $router_data = $this->engine('folder')->router($this->get('request')->getPathInfo());

//        ladybug_dump($router_data);

        foreach ($router_data['folders'] as $folder) {
            $this->engine('breadcrumbs')->add($folder['uri'], $folder['title'], $folder['descr']);
            if (isset($folder['router_response'])) {
                $mbc = $folder['router_response']->getBreadcrumbs();
                foreach ($mbc as $bc) {
                    $this->engine('breadcrumbs')->add($bc['uri'], $bc['title'], $bc['descr']);
                }
            }
        }

        $this->View->setOptions(array(
            'comment'       => 'Базовый шаблон',
            //'engine'        => 'twig',
            'template'      => $router_data['template'],
            'environment'   => array(
                'cache'         => $this->engine('env')->dir_cache . 'twig',
                'auto_reload'   => true,
                'autoescape'    => false,
                //'debug'          => true,
            ),
        ));

        $this->View->setPaths(array(
            $this->engine('env')->dir_web_root . 'theme/views', // @todo сделать через настройки.
            $this->engine('env')->dir_app . 'Resources/views',
            $this->container->get('kernel')->getBundle('SmartCoreEngineBundle')->getPath() . '/Resources/views',
        ));
        
        $this->View->html = $this->Html;
        $this->engine('html')->title('Smart Core CMS (based on Symfony2 Framework)');

        $theme_path = $this->engine('env')->base_path . $this->engine('env')->theme_path;
        $this->View->assets = array(
            'theme_path'        => $theme_path,
            'theme_css_path'    => $theme_path . 'css/',
            'theme_js_path'     => $theme_path . 'js/',
            'theme_img_path'    => $theme_path . 'images/',
            'vendor'            => $this->engine('env')->global_assets,
        );

        $this->engine('theme')->processConfig($this->View);

        foreach ($this->engine('JsLib')->all() as $lib => $res) {
            if (isset($res['js']) and is_array($res['js'])) {
                foreach ($res['js'] as $js) {
                    $this->engine('html')->js($js, 200);
                }
            }
            if (isset($res['css']) and is_array($res['css'])) {
                foreach ($res['css'] as $css) {
                    $this->engine('html')->css($css, 200);
                }
            }
        }

        $this->View->block = new View();
        //$this->View->block->setRenderMethod('echoProperties');
        $this->View->block->setOptions(array('comment' => 'Блоки'));

        $nodes_list = $this->engine('node')->buildNodesListByFolders($router_data['folders']);
//        $this->Node->buildNodesListByFolders($router_data['folders']);

//        sc_dump($nodes_list);

        $this->buildModulesData($nodes_list);
        
//        sc_dump($this->View->block);
        
//        sc_dump($this->View->block);
//        sc_dump($this->Html);

//        sc_dump($this->renderView("SmartCoreTexterModule::texter.html.twig", array('text' => 777)));
//        sc_dump($this->forward('SmartCoreTexterModule:Test:hello', array('text' => 'yahoo :)'))->getContent());
//        sc_dump($this->forward('2:Test:index')->getContent());

//        $tmp = $this->forward(8);
//        $tmp = $this->forward('SmartCoreMenuModule:Menu:index');
//        sc_dump(get_class($tmp));
//        sc_dump($tmp->getContentRaw());
        
//        echo $tmp->getContent();
        
//        exit;

//        $Test = $this->forward('SmartCoreTexterModule:Test:test', array('text' => 'test!!'))->getContent();
//        $Test = $this->forward('SmartCoreTexterModule:Test:test', array('text' => 'test!!'));
        
//        sc_dump($Test);
        
//        sc_dump($this->forward('SmartCoreTexterModule:Test:test', array('text' => 'test!!'))->getContent());

//        sc_dump($this->container->get('kernel')->getLogDir());

        if ($this->container->has('smart_core_engine.active_theme')) {
            $activeTheme = $this->container->get('smart_core_engine.active_theme');
            $activeTheme->setThemes(array('web', 'tablet', 'phone'));
//            $activeTheme->setName('tablet');
        }

        $View = $this->container->get('templating')->render("::{$this->View->getTemplateName()}.html.twig", array(
            'html' => $this->engine('Html'),
            'block' => $this->View->block,
        ));
                
//        sc_dump($this->engine('breadcrumbs'));
//        sc_dump($this->engine('env'));
//        sc_dump($this->engine('site')->getId());
//        sc_dump($this->getUser());
        return new Response($View, $router_data['status']);
    }
    
    /**
     * Сборка "блоков" из подготовленного списка нод.
     * По мере прохождения, подключаются и запускаются нужные модули с нужными параметрами.
     */
    protected function buildModulesData($nodes_list)
    {
        $blocks = $this->engine('block')->all();
        
        // Каждый "блок" является объектом вида.
        foreach ($blocks as $block_id => $block) {
            $this->View->block->$block['name'] = new View();
            $this->View->block->$block['name']->setRenderMethod('echoProperties');
        }

        define('_IS_CACHE_NODES', false); // @todo remove

        foreach ($nodes_list as $node_id => $node_properties) {
            // Не собираем ноду, если она уже была отработала в механизе nodeAction()
            if ($node_id == $this->front_end_action_node_id) {
                continue;
            }

            $block_name = $blocks[$node_properties['block_id']]['name'];

            // Обнаружены параметры кеша.
            if (_IS_CACHE_NODES and $node_properties['is_cached'] and !empty($node_properties['cache_params']) and $this->engine('env')->cache_enable ) {
                $cache_params = unserialize($node_properties['cache_params']);
                if (isset($cache_params['id']) and is_array($cache_params['id'])) {
                    $cache_id = array();
                    foreach ($cache_params['id'] as $key => $dummy) {
                        switch ($key) {
                            case 'current_folder_id':
                                $cache_id['current_folder_id'] = $this->engine('env')->current_folder_id;
                                break;
                            case 'user_id':
                                $cache_id['user_id'] = $this->engine('env')->user_id;
                                break;
                            case 'parser_data': // @todo route_data
                                $cache_id['parser_data'] = $node_properties['parser_data'];
                                break;
                            case 'request_uri':
                                $cache_id['parser_data'] = $_SERVER['REQUEST_URI'];
                                break;
                            case 'user_groups':
                                $user_data = $this->User->getData();
                                $cache_id['user_groups'] = $user_data['groups'];
                                break;
                            default;
                        }
                    }
                    $cache_params['id'] = $cache_id;
                }
                $cache_params['id']['node_id'] = $node_id;
                $cache_params['nodes'][$node_id] = 1;
            } else {
                $cache_params = null;
            }

            // Попытка взять HTML кеш ноды.
            if (_IS_CACHE_NODES
                and !empty($cache_params)
                and $this->Cookie->sc_frontend_mode !== 'edit'
                and $html_cache = $this->Cache_Node->loadHtml($cache_params['id'])
            ) {
                // $this->EE->data[$block_name][$node_id]['html_cache'] = $html_cache; @todo !!!!!!!!
            }
            // Кеша нет.
            else { 
                // Если разрешены права на запись ноды, то создаётся объект с административными методами и запрашивается у него данные для фронтальных элементов управления.
                /*
                if ($this->Permissions->isAllowed('node', 'write', $node_properties['permissions']) and ($this->Permissions->isRoot() or $this->Permissions->isAdmin()) ) {
                    $Module = $Node->getModuleInstance($node_id, true);
                } else {
                    $Module = $Node->getModuleInstance($node_id, false);
                }
                */
                
//                sc_dump($node_properties);

                $Module = $this->forward($node_id, array(
                    '_eip' => true,
                ));
                
//                sc_dump(get_class($Module));

                // Указать шаблонизатору, что надо сохранить эту ноду как html.
                // @todo ПЕРЕДЕЛАТЬ!!! подумать где выполнять кеширование, внутри объекта View или где-то снаружи.
                // @todo ВАЖНО подумать как тут поступить т.к. эта кука может стоять у гостя!!!
                if (_IS_CACHE_NODES and !empty($cache_params) and $this->Cookie->sc_frontend_mode !== 'edit') {
//                    $this->EE->data[$block_name][$node_id]['store_html_cache'] = $Module->getCacheParams($cache_params);
                } 

                // Получение данных для фронт-админки ноды.
                // @todo сделать нормальную проверку на возможность управления нодой. сейчас пока считается, что юзер с ИД = 1 имеет право админить.
                // @todo также тут надо учитывать режим Фронт-Админки. если он выключен, то вытягивать фронт-контролсы нет смысла.
                
                //if ($this->Permissions->isAllowed('node', 'write', $node_properties['permissions']) and $this->Cookie->sc_frontend_mode == 'edit') {
                if ( false ) {

                    $front_controls = $Module->getFrontControls();
                    
                    // Для рута добавляется пунктик "свойства ноды"
                    if ($this->Permissions->isRoot()) {
                        $front_controls['_node_properties'] = array(
                            'popup_window_title' => 'Свойства ноды' . " ( $node_id )",
                            'title'              => 'Свойства',
                            'link'               => HTTP_ROOT . ADMIN . '/structure/node/' . $node_id . '/?popup',
                            'ico'                => 'edit',
                        );
                    }

                    if(is_array($front_controls)) {
                        // @todo сделать выбор типа фронт админки popup/built-in/ajax.
                        $this->View->admin['frontend'][$node_id] = array(
                            // 'type' => 'popup',
                            'node_action_mode'  => $node_properties['node_action_mode'],
                            'doubleclick'       => '@todo двойной щелчок по блоку НОДЫ.',
                            'default_action'    => $Module->getFrontControlsDefaultAction(),
                            // элементы управления, относящиеся ко всей ноде.
                            'controls'          => $front_controls,
                            // элементы управления блоков внутри ноды.
                            //'controls_inner_default_action' = $Module->getFrontControlsInnerDefaultAction(),
                            'controls_inner'    => $Module->getFrontControlsInner(),
                        );
                    }

                    // @todo пока так выставляются декораторы обрамления ноды.
                    $Module->View->setDecorators("<div class=\"cmf-frontadmin-node\" id=\"_node$node_id\">", "</div>");
                }
            }
            
            if (method_exists($Module, 'getContentRaw')) {
                $this->View->block->$block_name->$node_id = $Module->getContentRaw();
            } else {
                $this->View->block->$block_name->$node_id = $Module->getContent();
            }

            unset($Module);
        }
    }
}