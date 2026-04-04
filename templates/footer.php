    </div><!-- .container -->
    
    <table border="1" width="100%" cellspacing="0" cellpadding="5" style="border-top: 1px solid #dee2e6; font-size: 0.75rem; color: #6c757d;">
        <tr>
            <td width="50%" align="left">
                &copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars(getSetting('site_name', 'EDUCN论坛')); ?>. 保留所有权利
            </td>
            <td width="50%" align="right">
                Powered by <a href="https://talk.gt.tc/" target="_blank"><?php echo htmlspecialchars(getSetting('forum_version', 'v0.2.0_t_260404')); ?></a>
            </td>
        </tr>
        <tr>
            <td colspan="2" align="center" style="padding: 5px;">
                <button onclick="showQRCode()" style="padding: 6px 12px; background-color: #f0f0f0; border: 1px solid #ddd; text-decoration: none; border-radius: 2px; cursor: pointer;">显示当前页面二维码</button>
                
                <!-- 二维码弹窗 -->
                <div id="qrcode-modal" style="display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.4);">
                    <div style="background-color: #fefefe; margin: 15% auto; padding: 20px; border: 1px solid #888; width: 80%; max-width: 300px; text-align: center;">
                        <span onclick="closeQRCode()" style="color: #aaa; float: right; font-size: 28px; font-weight: bold; cursor: pointer;">&times;</span>
                        <h3>当前页面二维码</h3>
                        <div style="border: 1px solid #ddd; padding: 10px; background-color: white; display: inline-block; margin: 10px 0;">
                            <?php
                                $current_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
                                $qr_url = 'https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=' . urlencode($current_url);
                            ?>
                            <img src="<?php echo $qr_url; ?>" alt="当前页面二维码" width="150" height="150">
                        </div>
                        <p>扫一扫分享当前页面</p>
                    </div>
                </div>
                
                <script>
                function showQRCode() {
                    document.getElementById('qrcode-modal').style.display = 'block';
                }
                
                function closeQRCode() {
                    document.getElementById('qrcode-modal').style.display = 'none';
                }
                
                // 点击弹窗外部关闭
                window.onclick = function(event) {
                    var modal = document.getElementById('qrcode-modal');
                    if (event.target == modal) {
                        modal.style.display = 'none';
                    }
                }
                </script>
            </td>
        </tr>
    </table>
</body>
</html>

