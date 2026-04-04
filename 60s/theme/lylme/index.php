<?php
/* 
 * @Description: 
 * @Author: LyLme admin@lylme.com
 * @Date: 2024-01-27 15:21:20
 * @LastEditors: LyLme admin@lylme.com
 * @LastEditTime: 2024-02-13 14:04:14
 * @FilePath: /LyToday/theme/lylme/index.php
 * @Copyright (c) 2024 by LyLme, All Rights Reserved. 
 */
include $config['theme'] . '/head.php';
?>

<body style="background-color: #e7e7e7;">

    <div class="hot-panel">
        <?php
        if ($config['day60s']) {
            echo ' <div class="hot-card">
                  <div class="hot-head">
                      <span class="hot-title">60秒读懂世界</span>
                      <span class="hot-unit">简讯</span>
                  </div>
                  <div class="hot-body">
                    <ul class="hot-table">
                  ';
            if (!empty($day60s)) {
                foreach ($day60s as $item) {
                    preg_match('/^(\d+)、(.+)；/', $item, $search);
                    if (array_key_exists(1, $search)) {
                        echo ' <li class="hot-list"><span class="hot-index">' . $search[1] . '</span><a href="https://www.wuzhuiso.com/s?q=' . urlencode($search[2]) . '" title="' . $item . '" target="_blank">' . $search[2] . ' </a></li>
                      ';
                    } else {
                        echo ' <li class="hot-list"><a href="#"">' . $item . '</a></li>';
                    }
                }
            } else {
                echo ' <li class="hot-list"><a href="#"> 获取数据失败</a></li>';
            }
            preg_match('/^(\d+)*/', json_decode(file_get_contents(SYSTEM_ROOT . 'data/60s_view/cache.json'), true)["latest"], $active_time);

            echo '</ul>
                    </div>
                    <div class="hot-footer"><span class="hot-time">最后更新：' . date("Y年m月d日", strtotime($active_time[1])) . '</span></div>
                </div>
                  ';
        }



        ?>
        <?php
        if ($config['hot']) {
            foreach ($hots as $hot) {
                echo ' <div class="hot-card">
                  <div class="hot-head">
                      <img src="' . $hot["logo"] . '">
                      <span class="hot-title">' . $hot["name"] . '</span>
                      <span class="hot-unit">' . $hot["unit"] . '</span>
                  </div>
                  <div class="hot-body">
                    <ul class="hot-table">
                  ';

                $slices = $hot['data'];
                foreach ($slices as $slice) {
                    echo '
                    <li class="hot-list"><span class="hot-index">' . $slice['id'] . '</span><a href="' . $slice['url'] . '" title="' . $slice['desc'] . '" target="_blank">' . $slice['title'] . '</a><span class="hot-rank">' . formatNumber($slice['hotScore']) . '</span><span class="hot-tag hot-tag-' . $slice['hotTag'] . '">' . $slice['hotTagName'] . '</span></li>
';
                }
                echo '
                    </ul>
                    </div>
                    <div class="hot-footer"><span class="hot-time">最后更新：' . formatTime($hot['active_time']) . '</span></div>
                </div>
                  ';
            }
        } else {
            echo '
            <div class="hot-card">
                <div class="hot-head">
                    <span class="hot-title">实时热搜</span>
                    <span class="hot-unit"></span>
                    <div class="hot-body">
                        <ul class="hot-table">
                            <li class="hot-list"></span><a href="#">错误：获取热搜数据失败！</a></li>
                        </ul>
                    </div>
                </div>
            </div>';
        }
        ?>

        <?php
        if ($config['history']) {
            echo ' <div class="hot-card">
                  <div class="hot-head">
                      <span class="hot-title">历史上的今天</span>
                      <span class="hot-unit"></span>
                  </div>
                  <div class="hot-body">
                    <ul class="hot-table">
                  ';

            foreach ($history_today as $item) {
                echo ' <li class="hot-list"><span class="span-year">' .  $item['year'] . '</span><a href="' . $item['link'] . '" title="' . $item['desc'] . '" target="_blank">' . $item['title'] . '</a></li>
                      ';
            }
            echo '</ul>
                    </div>
                    <div class="hot-footer"><span class="hot-time">数据来源：' . date("n月j日") . '</span></div>
                </div>
                  ';
        }
        ?>

        <?php if ($config['lunar']) { ?>
            <div class="hot-card">
                <div class="hot-head">
                    <span class="hot-title">今日黄历</span>
                    <span class="hot-unit"> <?php echo $lunar_md; ?></span>
                </div>
                <div class="hot-body">
                    <ul class="hot-table">
                        <li class="hot-list"><span class="span-lunar"><?php echo $lunar_ymd; ?></span> </li>
                        <li class="hot-list"><span class="span-lunar">五行</span> <?php echo $lunar_nayin; ?></li>
                        <li class="hot-list"><span class="span-lunar">冲煞</span><?php echo $lunar_chongsha; ?></li>
                        <li class="hot-list"><span class="span-lunar">彭祖</span><?php echo $lunar_pengzu; ?></li>
                        <li class="hot-list"><span class="span-lunar">喜神</span> <?php echo $lunar_xishen; ?></li>
                        <li class="hot-list"><span class="span-lunar">福神</span> <?php echo $lunar_fushen; ?></li>
                        <li class="hot-list"><span class="span-lunar">财神</span><?php echo $lunar_caishen; ?></li>
                        <li class="hot-list"><span class="span-lunar">吉神</span><?php echo implode('&#9;', $lunar_jshen); ?></li>
                        <li class="hot-list"><span class="span-lunar">凶神</span><?php echo implode('&#9;', $lunar_xshen); ?></li>
                        <li class="hot-list"><span class="span-lunar">
                                <font color="green">宜</font>
                            </span><?php echo implode('&#9;', $lunar_yi); ?></li>
                        <li class="hot-list"><span class="span-lunar">
                                <font color="red">忌</font>
                            </span><?php echo implode('&#9;', $lunar_ji); ?></li>
                    </ul>
                </div>
                <div class="hot-footer"><span class="hot-time"> <?php echo $lunar_ymd; ?></span></div>
            </div>


        <?php } ?>
        <?php if ($config['yan']) { ?>
            <div class="hot-card">
                <div class="hot-head">
                    <span class="hot-title">每日一语</span>
                    <span class="hot-unit"> </span>
                </div>
                <div class="hot-body">
                    <ul class="hot-table">
                        <li class="hot-list"><span class="span-yan"><?php echo yan() ?></span></li>
                    </ul>
                </div>
            </div>


        <?php } ?>
    </div> </div>
</body>

</html>
<?php
function formatTime($time)
{
    $now = time(); // 当前时间戳  
    $time = strtotime($time); // 将时间字符串转换为时间戳  
    $timeDifference = $now - $time; // 计算时间差  

    if ($timeDifference < 60) {
        return '刚刚';
    } elseif ($timeDifference < 3600) {
        $minutes = floor($timeDifference / 60);
        return $minutes . '分钟前';
    } elseif ($timeDifference < 86400) {
        $hours = floor($timeDifference / 3600);
        return $hours . '小时前';
    } elseif ($timeDifference < 201600) {
        $days = floor($timeDifference / 86400);
        if ($days == 1) {
            return '昨天';
        } else {
            return '前天';
        }
    } else {
        $date = date('Y-m-d', $time);
        return $date;
    }
} ?>