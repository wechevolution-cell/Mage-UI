<?= partial('sections/products', ['items' => $newest, 'title' => $sec['title'] ?: 'Νέες αφίξεις', 'id' => 'newest', 'more' => '/products/?sort=newest']) ?>
