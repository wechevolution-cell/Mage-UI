<?php
foreach ($hp['order'] as $section) {
    if (empty($hp[$section]['enabled'])) continue;
    echo partial('sections/' . $section, ['sec' => $hp[$section]]);
}
