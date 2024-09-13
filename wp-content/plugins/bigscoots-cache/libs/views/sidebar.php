<div id="bs_cache_sidebar">
  <div class="bs_cache_sidebar_widget">

    <div><img width="425" height="275" loading="lazy" src="<?php echo esc_url(BS_CACHE_PLUGIN_URL . 'assets/img/bigscoots-cloudflare.svg'); ?>" alt="BigScoots Logo" /></div>

    <div class="bs_cache_sidebar_widget_content">
      <?php if (defined('BS_MASTER_KEY') && defined('BS_SITE_ID') && defined('BS_MASTER_URL')) : // Already using CF ENT 
      ?>
        <h4 style="font-size:20px;margin-top:5px;margin-bottom:16px">Your BigScoots Performance Package!</h4>
        <p style="font-size:16px;line-height:1.6">The performance package works to greatly reduce and/or eliminate any problematic areas affecting your Page Speed and Core Web Vitals.</p>
        <h4 style="font-size:17px;margin-top:0;margin-bottom:16px">Currently Active:</h4>
        <ul style="list-style:square;margin-left:30px;font-size:16px;line-height:1.5">
          <li>Automated Image Optimization and WebP Delivery</li>
          <li>Edge Caching - Web pages are served on a Cloudflare server closest to the visitor.</li>
          <li>Premium Optimization Plugin</li>
          <li>Cloudflare Network Priority - Your site's assets receive preferred network routing across the Cloudflare network.</li>
          <li>Enterprise Level WAF Security and Anti-Spam Protection</li>
        </ul>
        <h4 style="font-size:16px;text-align:center;margin-top:0;margin-bottom:16px">Questions about your Performance Package?</h4>
        <a href="https://wpo.bigscoots.com/user/tickets/open" style="text-align: center; padding: 0 10px; margin-bottom: 10px" class="button button-primary" target="_blank">Get in touch!</a>
      <?php else : // Not using CF ENT 
      ?>
        <h4 style="font-size:20px;margin-top:5px;margin-bottom:16px">Get The Performance Package!</h4>
        <p style="font-size:16px;line-height:1.6">The BigScoots Performance Package will greatly reduce and/or eliminate any problematic areas affecting the speed and security of your site. A white-glove, done-for-you hands-on optimization performed by the BigScoots team</p>
        <h4 style="font-size:17px;margin-top:0;margin-bottom:16px">What You'll Get:</h4>
        <ul style="list-style:square;margin-left:30px;font-size:16px;line-height:1.5">
          <li>Improved Google PSI Performance Score</li>
          <li>Enhanced Core Web Vital Metrics</li>
          <li>Premium Optimization Plugin</li>
          <li>Cloudflare Network Priority - Your site's assets receive preferred network routing across the Cloudflare network.</li>
          <li>Enterprise Level WAF Security and Anti-Spam Protection</li>
        </ul>
        <a href="https://www.bigscoots.com/wordpress-speed-optimization/" style="text-align: center; padding: 0 10px; margin-bottom: 10px" class="button button-primary" target="_blank">Learn More!</a>
      <?php endif; ?>
    </div>

  </div>
</div>