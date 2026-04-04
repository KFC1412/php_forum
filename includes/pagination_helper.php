<?php
/**
 * 修改generatePagination函数以支持伪静态URL
 */
function generatePagination($current_page, $total_pages, $url_pattern) {
    $html = '<div style="display: flex; justify-content: center; align-items: center; gap: 5px;">';
    
    // 上一页
    if ($current_page > 1) {
        $html .= '<a href="' . sprintf($url_pattern, $current_page - 1) . '" style="padding: 5px 10px; border: 1px solid #ddd; text-decoration: none; color: #333;">&laquo; 上一页</a>';
    } else {
        $html .= '<span style="padding: 5px 10px; border: 1px solid #ddd; color: #999; cursor: not-allowed;">&laquo; 上一页</span>';
    }
    
    // 页码
    $start = max(1, $current_page - 2);
    $end = min($total_pages, $current_page + 2);
    
    if ($start > 1) {
        $html .= '<a href="' . sprintf($url_pattern, 1) . '" style="padding: 5px 10px; border: 1px solid #ddd; text-decoration: none; color: #333;">1</a>';
        if ($start > 2) {
            $html .= '<span style="padding: 5px 10px; border: 1px solid #ddd; color: #999;">...</span>';
        }
    }
    
    for ($i = $start; $i <= $end; $i++) {
        if ($i == $current_page) {
            $html .= '<a href="#" style="padding: 5px 10px; border: 1px solid #0066cc; background-color: #0066cc; color: white; text-decoration: none;">' . $i . '</a>';
        } else {
            $html .= '<a href="' . sprintf($url_pattern, $i) . '" style="padding: 5px 10px; border: 1px solid #ddd; text-decoration: none; color: #333;">' . $i . '</a>';
        }
    }
    
    if ($end < $total_pages) {
        if ($end < $total_pages - 1) {
            $html .= '<span style="padding: 5px 10px; border: 1px solid #ddd; color: #999;">...</span>';
        }
        $html .= '<a href="' . sprintf($url_pattern, $total_pages) . '" style="padding: 5px 10px; border: 1px solid #ddd; text-decoration: none; color: #333;">' . $total_pages . '</a>';
    }
    
    // 下一页
    if ($current_page < $total_pages) {
        $html .= '<a href="' . sprintf($url_pattern, $current_page + 1) . '" style="padding: 5px 10px; border: 1px solid #ddd; text-decoration: none; color: #333;">下一页 &raquo;</a>';
    } else {
        $html .= '<span style="padding: 5px 10px; border: 1px solid #ddd; color: #999; cursor: not-allowed;">下一页 &raquo;</span>';
    }
    
    $html .= '</div>';
    
    return $html;
}
