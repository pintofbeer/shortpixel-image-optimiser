<?php
namespace ShortPixel;
use ShortPixel\ShortpixelLogger\ShortPixelLogger as Log;
use ShortPixel\Notices\NoticeController as Notices;


// Future contoller for the edit media metabox view.
class OtherMediaViewController extends ShortPixelController
{
      //$this->model = new
      protected $template = 'view-other-media';
      protected $model = 'image';

      // Pagination .
      protected $items_per_page = 20;
      protected $currentPage = 1;
      protected $total_items = 0;
      protected $order;
      protected $orderby;
      protected $search;

      protected $actions = array();

      public function __construct()
      {
        $this->loadModel($this->model);
      //  $this->loadModel('image');
        parent::__construct();
        $this->setActions(); // possible actions.

        $this->currentPage = isset($_GET['paged']) ? intval($_GET['paged']) : 1;
        $this->total_items = intval($this->record_count());
        $this->orderby = ( ! empty( $_GET['orderby'] ) ) ? sanitize_text_field($_GET['orderby']) : 'id';
        $this->order = ( ! empty($_GET['order'] ) ) ? sanitize_text_field($_GET['order']) : 'desc'; // If no order, default to asc
        $this->search =  (isset($_GET["s"]) && strlen($_GET["s"]))  ? sanitize_text_field($_GET['s']) : false;

      }

      /** Controller default action - overview */
      public function load()
      {
          $this->process_actions();

          $this->view->items = $this->getItems();
          $this->view->headings = $this->getHeadings();
          $this->view->pagination = $this->getPagination();
          $this->view->filter = $this->getFilter();

          $this->checkQueue();
          $this->loadView();
      }

      public function renderNewActions($item_id)
      {
         $item = new \stdClass; // mock class to mimick the records used in the controller
         $item->id = $item_id;

         $spMetaDao = \wpSPIO()->getShortPixel()->getSpMetaDao();
         $metaModel = $spMetaDao->getMeta($item_id);  // returns shortpixelMeta object
         if (is_null($metaModel))
          return '';

         $item->status = $metaModel->getStatus();
         $item->compression_type = $metaModel->getCompressionType();

         $fs = \wpSPIO()->filesystem();
         $file = $fs->getFile($metaModel->getPath());

         $actions = $this->getDisplayActions($this->getActions($item, $file));

         return $actions;
      }

      //push to the processing list the pending ones, just in cas
      protected function checkQueue()
      {
          $sp = \wpSPIO()->getShortPixel();
          foreach ($this->view->items as $item) {
              if($item->status == \ShortPixelMeta::FILE_STATUS_PENDING ){
                  Log::addDebug('Adding pending files to processing - ' . $item->id);
                  $sp->getPrioQ()->push(\ShortPixelMetaFacade::queuedId(\ShortPixelMetaFacade::CUSTOM_TYPE, $item->id));
              }
          }
      }


      protected function setActions()
      {
        $nonce = wp_create_nonce( 'sp_custom_action' );
        $actions = array(
            'optimize' => array('action' => 'optimize', '_wpnonce' => $nonce , 'text' => __('Optimize now','shortpixel-image-optimiser')),

            'retry' => array('action' => 'optimize', '_wpnonce' => $nonce, 'text' =>  __('Retry','shortpixel-image-optimiser')),

            'redolossless' => array('action' => 'redo', '_wpnonce' => $nonce, 'type' => 'lossless', 'text' => __('Re-optimize lossless','shortpixel-image-optimiser')),

            'redolossy' => array('action' => 'redo', '_wpnonce' => $nonce, 'type' => 'lossy', 'text' => __('Re-optimize lossy','shortpixel-image-optimiser')),

            'redoglossy' => array('action' => 'redo', '_wpnonce' => $nonce, 'type' => 'glossy', 'text' => __('Re-optimize glossy','shortpixel-image-optimiser')),

            'quota' => array('action' => 'quota', '_wpnonce' => $nonce, 'text' =>__('Check quota','shortpixel-image-optimiser')),

            'restore' => array('action' => 'restore', '_wpnonce' => $nonce, 'text' => __('Restore','shortpixel-image-optimiser')),

            'compare' => array('link' => '<a href="javascript:ShortPixel.loadComparer(\'C-%%item_id%%\');">%%text%%</a>"',
                      'text' => __('Compare', 'shortpixel-image-optimiser')),
            'view' => array('link' => '<a href="%%item_url%%" target="_blank">%%text%%</a>', 'text' => __('View','shortpixel-image-optimiser')),
        );
        $this->actions = $actions;
      }

      protected function getHeadings()
      {
         $headings = array(
              'thumbnails' => array('title' => __('Thumbnails', 'shortpixel-image-optimiser'),
                              'sortable' => false,
                            ),
               'name' =>  array('title' => __('Name', 'shortpixel-image-optimiser'),
                                'sortable' => true,
                                'orderby' => 'name',
                            ),
               'folder' => array('title' => __('Folder', 'shortpixel-image-optimiser'),
                                'sortable' => true,
                                'orderby' => 'path',
                            ),
               'type' =>   array('title' => __('Type', 'shortpixel-image-optimiser'),
                                'sortable' => false,
                                ),
               'date' =>    array('title' => __('Date', 'shortpixel-image-optimiser'),
                                'sortable' => true,
                                'orderby' => 'ts_optimized',
                             ),
               'status' => array('title' => __('Status', 'shortpixel-image-optimiser'),
                                'sortable' => true,
                                'orderby' => 'status',
                            ),
               'actions' => array('title' => __('Actions', 'shortpixel-image-optimiser'),
                                 'sortable' => false,
                            ),
        );

        return $headings;
      }

      protected function getItems()
      {
          $spMetaDao = \wpSPIO()->getShortPixel()->getSpMetaDao();
          //$total_items  =

          // [BS] Moving this from ts_added since often images get added at the same time, resulting in unpredictable sorting
          $items = $spMetaDao->getPaginatedMetas(\wpSPIO()->env()->has_nextgen, $this->getFilter(), $this->items_per_page, $this->currentPage, $this->orderby, $this->order);

          return $items;
      }

      protected function getFilter() {
          $filter = array();
          if(isset($_GET["s"]) && strlen($_GET["s"])) {
              $filter['path'] = (object)array("operator" => "like", "value" =>"'%" . esc_sql($_GET["s"]) . "%'");
          }
          return $filter;
      }

      protected function record_count() {
          $spMetaDao = \wpSPIO()->getShortPixel()->getSpMetaDao();
          return $spMetaDao->getCustomMetaCount($this->getFilter());
      }

      protected function process_actions()
      {

        $nonce = isset($_REQUEST['_wpnonce']) ? esc_attr($_REQUEST['_wpnonce']) : false;
        $redirect_url = esc_url_raw(remove_query_arg(array('action', 'image', '_wpnonce')));
        $action = isset($_REQUEST['action']) ? sanitize_text_field($_REQUEST['action']) : false;
        $item_id = isset($_REQUEST['item_id']) ? intval($_REQUEST['item_id']) : false;
        $this->view->rewriteHREF = '';

        $otherMediaController = new OtherMediaController();

        if (! $action)
         return; // no action this view.

        if (!wp_verify_nonce($nonce, 'sp_custom_action'))
        {
         die('Error. Nonce not verified. Do not call this function directly');
        }

        if ( $item_id === false && $action && $action != 'refresh')
        {
          exit('Error. No Item_id given');
        }

        switch ($action)
        {
            case 'optimize':
                $this->shortPixel->optimizeCustomImage($item_id);
                $this->rewriteHREF();

            break;
            case 'restore':
                if($this->shortPixel->doCustomRestore($item_id))
                {
                  Notices::addSuccess(__('File Successfully restored', 'shortpixel-image-optimiser'));
                }
                $this->rewriteHREF();
            break;
            case 'redo':
              $this->shortPixel->redo('C-' . $item_id, sanitize_text_field($_GET['type']));
              $this->rewriteHREF();

            break;
            case 'refresh':
                $result = $otherMediaController->refreshFolders(true);
                if ($result)
                {
                  Notices::addSuccess(__('Other media folders fully refreshed and updated', 'shortpixel-image-optimiser'));
                  $this->rewriteHREF();
                }
            break;
            case 'bulk-optimize': // bulk action checkboxes
              $optimize_ids = esc_sql($_POST['bulk-optimize']);
              foreach ($optimize_ids as $id) {
                 $this->shortPixel->optimizeCustomImage($id);
              }
              $this->rewriteHREF();
            break;
        }
      }

      /** This is a workaround for doing wp_redirect when doing an action, which doesn't work due to the route. Long-term fix would be using Ajax for the actions */
      protected function rewriteHREF()
      {
          $rewrite = $this->url; //isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] :
          $this->view->rewriteHREF = '<script language="javascript"> history.pushState(null,null, "' . $rewrite . '"); </script>';
      }


      protected function getPageURL($args = array())
      {
        $defaults = array(
            'orderby' => $this->orderby,
            'order' => $this->order,
            's' => $this->search,
            'paged' => $this->currentPage
        );

        // Try with controller URL, if not present, try with upload URL and page param.
        $admin_url = admin_url('upload.php');
        $url = (is_null($this->url)) ?  add_query_arg('page','wp-short-pixel-custom', $admin_url) : $this->url;

        $page_args = array_filter(wp_parse_args($args, $defaults));
        return add_query_arg($page_args, $url);

      }

      protected function getPagination()
      {
          $parray = array();

          $current = $this->currentPage;
          $total = $this->total_items;
          $per_page = $this->items_per_page;

          $pages = round($total / $per_page);

          if ($pages <= 1)
            return ''; // no pages.

          $disable_first = $disable_last = $disable_prev =  $disable_next = false;
          $page_links = array();

           if ( $current == 1 ) {
               $disable_first = true;
               $disable_prev  = true;
           }
           if ( $current == 2 ) {
               $disable_first = true;
           }
           if ( $current == $pages ) {
               $disable_last = true;
               $disable_next = true;
           }
           if ( $current == $pages - 1 ) {
               $disable_last = true;
           }

           $total_pages_before = '<span class="paging-input">';
           $total_pages_after  = '</span></span>';

           $current_url = remove_query_arg( 'paged', $this->getPageURL());

           $output = '<form method="GET" action="'. $current_url . '">'; //'<span class="pagination-links">';
           $output .= '<span class="displaying-num">'. sprintf(__('%d Items', 'shortpixel-image-optimiser'), $this->total_items) . '</span>';

           if ( $disable_first ) {
                    $page_links[] = '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">&laquo;</span>';
                } else {
                    $page_links[] = sprintf(
                        "<a class='first-page button' href='%s'><span class='screen-reader-text'>%s</span><span aria-hidden='true'>%s</span></a>",
                        esc_url( $current_url ),
                        __( 'First page' ),
                        '&laquo;'
                    );
                }

            if ( $disable_prev ) {
                $page_links[] = '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">&lsaquo;</span>';
            } else {
                $page_links[] = sprintf(
                    "<a class='prev-page button' href='%s'><span class='screen-reader-text'>%s</span><span aria-hidden='true'>%s</span></a>",
                    esc_url( add_query_arg( 'paged', max( 1, $current - 1 ), $current_url ) ),
                    __( 'Previous page' ),
                    '&lsaquo;'
                );
            }

            $html_current_page = sprintf(
                "%s<input class='current-page' id='current-page-selector' type='text' name='paged' value='%s' size='%d' aria-describedby='table-paging' /><span class='tablenav-paging-text'>",
                '<label for="current-page-selector" class="screen-reader-text">' . __( 'Current Page' ) . '</label>',
                $current,
                strlen( $pages )
            );

            $html_total_pages = sprintf( "<span class='total-pages'>%s</span>", number_format_i18n( $pages ) );
            $page_links[]     = $total_pages_before . sprintf(
                /* translators: 1: Current page, 2: Total pages. */
                _x( '%1$s of %2$s', 'paging' ),
                $html_current_page,
                $html_total_pages
            ) . $total_pages_after;

            if ( $disable_next ) {
                $page_links[] = '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">&rsaquo;</span>';
            } else {
                $page_links[] = sprintf(
                    "<a class='next-page button' href='%s'><span class='screen-reader-text'>%s</span><span aria-hidden='true'>%s</span></a>",
                    esc_url( add_query_arg( 'paged', min( $pages, $current + 1 ), $current_url ) ),
                    __( 'Next page' ),
                    '&rsaquo;'
                );
            }

            if ( $disable_last ) {
                $page_links[] = '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">&raquo;</span>';
            } else {
                $page_links[] = sprintf(
                    "<a class='last-page button' href='%s'><span class='screen-reader-text'>%s</span><span aria-hidden='true'>%s</span></a>",
                    esc_url( add_query_arg( 'paged', $pages, $current_url ) ),
                    __( 'Last page' ),
                    '&raquo;'
                );
            }

            $output .= "\n<span class='pagination-links'>" . join( "\n", $page_links ) . '</span>';
            $output .= "</form>";


          return $output;
      }

      /** Actions to list under the Image row */
      protected function getRowActions($item, $file)
      {
          $thisActions = array();
          $thisActions[] = $this->actions['view']; // always .
          $settings = \wpSPIO()->settings();

          if ($settings->quotaExceeded)
          {
            return $this->renderActions($thisActions, $item, $file); // nothing more.
          }

          if ($item->status < \ShortPixelMeta::FILE_STATUS_UNPROCESSED)
          {
            $thisActions[] = $this->actions['retry'];
          }
          elseif ($item->status == \ShortPixelMeta::FILE_STATUS_UNPROCESSED || $item->status == \ShortPixelMeta::FILE_STATUS_RESTORED)
          {
            $thisActions[] = $this->actions['optimize'];
          }

          return $this->renderActions($thisActions, $item, $file);
      }

      /* Actions to list in the action menu */
      protected function getActions($item, $file)
      {
         $thisActions = array();
         $settings = \wpSPIO()->settings();

         if ($settings->quotaExceeded)
         {
           $thisActions[] = $this->actions['quota'];
         }
         elseif ($item->status < \ShortPixelMeta::FILE_STATUS_UNPROCESSED)
         {
           $thisActions[] = $this->actions['retry'];
         }
         elseif ($item->status == \ShortPixelMeta::FILE_STATUS_UNPROCESSED || $item->status == \ShortPixelMeta::FILE_STATUS_RESTORED)
         {
           $thisActions[] = $this->actions['optimize'];
         }
         elseif ( intval($item->status) == \ShortPixelMeta::FILE_STATUS_SUCCESS && $file->hasBackup() )
         {

           $thisActions[] = $this->actions['restore'];
           switch($item->compression_type) {
               case 2:
                   $actionsEnabled['redolossy'] = $actionsEnabled['redolossless'] = true;
                   $thisActions[] = $this->actions['redolossy'];
                   $thisActions[] = $this->actions['redolossless'];
                   break;
               case 1:
                   $actionsEnabled['redoglossy'] = $actionsEnabled['redolossless'] = true;
                   $thisActions[] = $this->actions['redoglossy'];
                   $thisActions[] = $this->actions['redolossless'];
                   break;
               default:
                   $thisActions[] = $this->actions['redolossy'];
                   $thisActions[] = $this->actions['redoglossy'];
               break;
           }
         }

         return $this->renderActions($thisActions, $item, $file);
      }

      protected function renderActions($actions, $item, $file)
      {

        foreach($actions as $index => $action)
        {
          $text = $action['text'];

          if (isset($action['link']))
          {
             $fs = \wpSPIO()->filesystem();
             $item_url = $fs->pathToUrl($file);

             $link = $action['link'];
             $link = str_replace('%%item_id%%', $item->id, $link);
             $link = str_replace('%%text%%', $text, $link);
             $link = str_replace('%%item_url%%', $item_url, $link);
          }
          else
          {
              $action_arg = $action['action']; //
              $nonce = $action['_wpnonce'];
              $url = $this->getPageURL(array('action' => $action_arg, '_wpnonce' => $nonce, 'item_id' => $item->id));
              if (isset($action['type']))
                $url = add_query_arg('type', $action['type'], $url);

              $link = '<a href="' . $url . '" class="action-' . $action_arg . '">' . $text . '</a>';
          }

          $actions[$index] = $link;
        }

        return $actions;
      }

      protected function renderLegacyCell()
      {

        $data = $this->data;

        if ( $data['status'] != 'pdfOptimized' && $data['status'] != 'imgOptimized')
          return null;

        $this->legacyViewObj->renderListCell($this->post_id, $data['status'], $data['showActions'], $data['thumbsToOptimize'],
                $data['backup'], $data['type'], $data['invType'], '');
      }

      protected function getDisplayStatus($item)
      {
        switch($item->status) {
            case \ShortPixelMeta::FILE_STATUS_RESTORED:
              $msg = __('Restored','shortpixel-image-optimiser');
            break;
            case \ShortPixelMeta::FILE_STATUS_TORESTORE:
              $msg = __('Restore Pending','shortpixel-image-optimiser');
            break;
            case \ShortPixelMeta::FILE_STATUS_SUCCESS:
                $msg = $this->getSuccessMessage($item);
            break;
            case 1: $msg = "<img src=\"" . wpSPIO()->plugin_url('res/img/loading.gif') . "\" class='sp-loading-small'>&nbsp;"
                           . __('Pending','shortpixel-image-optimiser');
                break;
            case 0: $msg = __('Image not processed.','shortpixel-image-optimiser');
                break;
            default:
                if($item->status < 0) {
                    $msg = $item->message . "(" . __('code','shortpixel-image-optimiser') . ": " . $item->status . ")";
                } else {
                    $msg = "<span style='display:none;'>" . $item->status . "</span>";
                }
        }
        return  $msg;

      }

      protected function getSuccessMessage($item)
      {
        $msg = '';

        $amount = intval($item->message);
        if (0 + $amount == 0 || 0 + $amount < 5)
            $msg .= __('Bonus processing','shortpixel-image-optimiser') .  ' ';
        else
            $msg .= __('Reduced by','shortpixel-image-optimiser') . " <strong>" . $item->message . "%</strong> ";

        switch($item->compression_type)
        {
          case \ShortPixelMeta::COMPRESSION_LOSSY:
             $msg .= '(' . __('Lossy', 'shortpixel-image-optimiser') . ')';
          break;
          case \ShortPixelMeta::COMPRESSION_GLOSSY:
              $msg .= '(' . __('Glossy', 'shortpixel-image-optimiser') . ')';
          break;
          case \ShortPixelMeta::COMPRESSION_LOSSLESSS:
              $msg .= '(' . __('Lossless', 'shortpixel-image-optimiser') . ')';
          break;
        }

        if ($item->resize)
        {
           $msg .= '<br>' . sprintf(__('Resized to %s x %s', 'shortpixel-image-optimiser'), $item->resize_width, $item->resize_height);
        }
        return $msg;

      }

      protected function getDisplayHeading($heading)
      {
          $output = '';
          $defaults = array('title' => '', 'sortable' => false);

          $heading = wp_parse_args($heading, $defaults);
          $title = $heading['title'];

          if ($heading['sortable'])
          {
              //$current_order = isset($_GET['order']) ? $current_order : false;
              //$current_orderby = isset($_GET['orderby']) ? $current_orderby : false;

              $sorturl = add_query_arg('orderby', $heading['orderby'] );
              $sorted = '';

              if ($this->orderby == $heading['orderby'])
              {
                if ($this->order == 'desc')
                {
                  $sorturl = add_query_arg('order', 'asc', $sorturl);
                  $sorted = 'sorted desc';
                }
                else
                {
                  $sorturl = add_query_arg('order', 'desc', $sorturl);
                  $sorted = 'sorted asc';
                }
              }
              else
              {
                $sorturl = add_query_arg('order', 'asc', $sorturl);
              }
              $output = '<a href="' . $sorturl . '"><span>' . $title . '</span><span class="sorting-indicator '. $sorted . '">&nbsp;</span></a>';
          }
          else
          {
            $output = $title;
          }

          return $output;
      }

      protected function getDisplayDate($item)
      {
        if ($item->ts_optimized > 0)
          $date_string = $item->ts_optimized;
        else
          $date_string = $item->ts_added;

        $date = new \DateTime($date_string);

        $display_date = \ShortPixelTools::format_nice_date($date);

         return $display_date;
      }

      protected function getDisplayActions($actions)
      {
           if (count($actions) == 0)
           {
             return '';
           }
           elseif (count($actions) == 1)
            {
              return "<div class='single-action button-primary'>" . $actions[0] . "</div>";
            }
            else{

            $output = "<div class='sp-dropdown'>
                <button onclick='ShortPixel.openImageMenu(event);' class='sp-dropbtn button dashicons dashicons-menu' title='" .  __('ShortPixel Actions', 'shortpixel-image-optimiser') . "'></button>
                <div class='sp-dropdown-content'>";
              foreach($actions as $action)
              {
                  $output .= $action;
              }
              $output .= "</div></div> <!-- sp-dropdown -->";
              return $output;
            }
      }


}
