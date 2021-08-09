<?php

namespace Dockworker;

/**
 * Provides methods to interact with a Drupal codebase.
 */
trait DrupalDrushSqlDumpTrait {

  public function getDrushDumpCommand() {
    return 'sql-dump --extra-dump=--no-tablespaces --structure-tables-list="accesslog,batch,cache,cache_*,ctools_css_cache,ctools_object_cache,flood,search_*,history,queue,semaphore,sessions,watchdog,webform_submitted_data"';
  }

}
