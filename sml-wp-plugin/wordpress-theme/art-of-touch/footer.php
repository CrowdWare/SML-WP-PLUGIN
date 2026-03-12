<footer>
    <div class="footer-bar">
        <div class="container">
            <span class="copyright">(C) <?php echo esc_html(date('Y')); ?> Olaf Japp. All Rights Reserved.</span>
            <a class="toTop" href="#topNav">NACH OBEN <i class="fa fa-arrow-circle-up"></i></a>
        </div>
    </div>

    <div class="footer-content">
        <div class="container">
            <div class="row">
                <div class="column col-md-4" id="contact">
                    <h3>CONTACT</h3>
                    <p class="contact-desc">If you have any questions about offerings, contact me via email, SMS or telephone.</p>
                    <address class="font-opensans">
                        <ul>
                            <li class="footer-sprite address">Olaf Japp<br />Lutherstadt-Wittenberg<br /></li>
                            <li class="footer-sprite phone">Tel/SMS: +49 3491 6449633<br />Telegram: <a href="https://t.me/artanidos">@artanidos</a></li>
                            <li class="footer-sprite email"><a href="mailto:japp.olaf@gmail.com">japp.olaf@gmail.com</a></li>
                            <li class="footer-sprite address">Banking:<br />Olaf Japp<br />IBAN: DE26 8055 0101 1401 3850 59<br />BIC: NOLADE21WBL</li>
                        </ul>
                    </address>
                </div>

                <div class="column logo col-md-4 text-center">
                    <div class="logo-content">
                        <img class="animate_fade_in" src="<?php echo esc_url(get_template_directory_uri() . '/assets/images/logo_footer.png'); ?>" width="200" alt="" />
                        <h4>ART OF TOUCH</h4>
                    </div>
                </div>

                <div class="column col-md-4">
                    <h3>POWERED BY</h3>
                    <p class="contact-desc">This website has been built using the following tools.</p>
                    <address class="font-opensans">
                        <ul>
                            <li><a href="https://artanidos.github.io/FlatSiteBuilder/"><i class="fa fa-info"></i><span>&nbsp;&nbsp;&nbsp;FlatSiteBuilder</span></a></li>
                            <li><a href="https://wrapbootstrap.com/user/stepofweb"><i class="fa fa-info"></i><span>&nbsp;&nbsp;&nbsp;Atropos Theme</span></a></li>
                        </ul>
                    </address>
                </div>
            </div>
        </div>
    </div>
</footer>

<?php wp_footer(); ?>
</body>
</html>
