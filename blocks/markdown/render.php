<?php
if (!defined('ABSPATH')) exit;

$content = $attributes['content'] ?? '';
if (empty($content)) return;

echo '<div ' . get_block_wrapper_attributes(['class' => 'wipress-markdown-block']) . '>';
echo Wipress_Markdown::render($content);
echo '</div>';
