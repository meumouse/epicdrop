<?php

if (! defined('ABSPATH')) {
	exit; // Exit if accessed directly
}


/**
 * Class global hosts
 * 
 * @return array
 * @since 1.0.0
 * 
 */
class Woo_EpicDrop_Hosts {

    public static function getImportHosts() {

        return array(
            array(
                'name' => '<b>Amazon.com</b>, Amazon.com.au, Amazon.com.br, Amazon.ca, Amazon.cn, Amazon.fr, Amazon.de, Amazon.in, Amazon.it, Amazon.co.jp, Amazon.com.mx, Amazon.nl, Amazon.es, Amazon.co.uk, Amazon.ae, Amazon.sa, Amazon.se, Amazon.pl, Amazon.sg, Amazon.com.tr, Amazon.eg',
                'parser' => 'AmazonParser',
                'valid_url' => '/amazon\.(.+)\/(dp|gp)\//'
            ),
            array(
                'name' => '<b>ebay.com</b>, ebay.com.au, ebay.at, benl.ebay.be, befr.ebay.be, ebay.ca, ebay.cn, ebay.fr, ebay.de, ebay.ie, ebay.it, ebay.com.hk, ebay.com.my, ebay.nl, ebay.ph, ebay.pl, ebay.com.sg, ebay.es, ebay.ch, ebay.co.uk, ebay.vn',
                'parser' => 'EbayParser',
                'valid_url' => '/ebay\.(.+)\/itm\//'
            ),
            array(
                'name' => '<b>www.aliexpress.com</b>, id.aliexpress.com, ar.aliexpress.com, de.aliexpress.com, es.aliexpress.com, fr.aliexpress.com, it.aliexpress.com, ja.aliexpress.com, ko.aliexpress.com, nl.aliexpress.com, pt.aliexpress.com, aliexpress.ru, th.aliexpress.com, tr.aliexpress.com, vi.aliexpress.com, he.aliexpress.com',
                'parser' => 'AliexpressParser',
                'valid_url' => '/aliexpress\.(.+)\/item\//'
            ),
            array(
                'name' => '<b>Walmart.com</b>, super.walmart.com.mx, walmart.com.mx, walmart.com.mx',
                'parser' => 'WalmartParser',
                'valid_url' => '/walmart\.(.+)/'
            ),
            array(
                'name' => '<b>Flipkart.com</b>',
                'parser' => 'FlipkartParser',
                'valid_url' => '/flipkart\.(.+)\/p\//'
            ),
            array(
                'name' => '<b>Shein.com</b>, fr.shein.com, ca.shein.com, il.shein.com, au.shein.com, de.shein.com, ru.shein.com, eur.shein.com, br.shein.com, asia.shein.com, cl.shein.com, id.shein.com, it.shein.com, jp.shein.com, ar.shein.com, my.shein.com, shein.com.mx, nl.shein.com, nz.shein.com, ph.shein.com, pl.shein.com, pt.shein.com, us.shein.com, sg.shein.com, za.shein.com, es.shein.com, shein.se, shein.in, ch.shein.com, shein.tw, th.shein.com, shein.co.uk, shein.com.vn, shein.com.hk',
                'parser' => 'SheinParser',
                'valid_url' => '/\.shein\.(.+)-p-/'
            ),
            array(
                'name' => '<b>Allegro.pl</b>',
                'parser' => 'AllegroParser',
                'valid_url' => '/allegro\.pl\//'
            ),
            array(
                'name' => '<b>Trendyol.com</b>',
                'parser' => 'TrendyolParser',
                'valid_url' => '/trendyol\.(.+)-p-/'
            ),
            array(
                'name' => '<b>Mercadolibre.com</b>, mercadolibre.com.ar, mercadolibre.com.bo, mercadolivre.com.br, mercadolibre.cl, mercadolibre.com.co, mercadolibre.co.cr, mercadolibre.com.do, mercadolibre.com.ec, mercadolibre.com.gt, mercadolibre.com.hn, mercadolibre.com.mx, mercadolibre.com.ni, mercadolibre.com.pa, mercadolibre.com.py, mercadolibre.com.pe, mercadolibre.com.sv, mercadolibre.com.uy, mercadolibre.com.ve',
                'parser' => 'MercadolibreParser',
                'valid_url' => '/mercadoli(.*)re\.(.+)/'
            ),
            array(
                'name' => '<b>Wildberries.ru</b>, by.Wildberries.ru, kz.wildberries.ru, am.wildberries.ru, ee.wildberries.ru, lv.wildberries.ru, it.wildberries.ru, md.wildberries.ru, us.wildberries.ru, wildberries.fr, it.wildberries.eu, wildberries.es, de.wildberries.eu, wildberries.co.il, ee.wildberries.ru, wildberries.ua, sk.wildberries.eu',
                'parser' => 'WildberriesParser',
                'valid_url' => '/wildberries\.(.+)[\/detail\.aspx|card=]/'
            ),
            array(
                'name' => '<b>Target.com</b>',
                'parser' => 'TargetParser',
                'valid_url' => '/target\.(.+)/'
            ),
            array(
                'name' => '<b>Americanas.com.br</b>',
                'parser' => 'AmericanasParser',
                'valid_url' => '/americanas\.(.+)\/produto\//'
            ),
            array(
                'name' => '<b>Lazada.com</b>, lazada.vn, lazada.com.my, lazada.co.th, lazada.sg, lazada.com.ph, lazada.co.id',
                'parser' => 'LazadaParser',
                'valid_url' => '/lazada\.(.+)\/products\//'
            ),
            array(
                'name' => '<b>Hepsiburada.com</b>',
                'parser' => 'HepsiburadaParser',
                'valid_url' => '/hepsiburada\.(.+)(-pm-|-p-)/'
            ),
            array(
                'name' => '<b>Wish.com</b>',
                'parser' => 'WishParser',
                'valid_url' => '/wish\.(.+)\/product\//'
            ),
            array(
                'name' => '<b>Wayfair.com</b>, wayfair.ca, wayfair.co.uk, wayfair.de',
                'parser' => 'WayfairParser',
                'valid_url' => '/wayfair\.(.+)\/pdp\//'
            ),
            array(
                'name' => '<b>Etsy.com</b>',
                'parser' => 'EtsyParser',
                'valid_url' => '/etsy\.(.+)/'
            ),
            array(
                'name' => '<b>Jd.com</b>',
                'parser' => 'JdParser',
                'valid_url' => '/jd\.(.+)/'
            ),
            array(
                'name' => '<b>Shopee.com</b>, shopee.co.id, shopee.tw, shopee.vn, shopee.co.th, shopee.ph, shopee.com.my, shopee.sg, shopee.com.br, shopee.com.mx, shopee.com.co, shopee.cl, shopee.pl, shopee.es, shopee.fr, shopee.in',
                'parser' => 'ShopeeParser',
                'valid_url' => '/shopee\.(.+)(-i\.|product\/)/'
            ),
            array(
                'name' => '<b>Bol.com</b>',
                'parser' => 'BolParser',
                'valid_url' => '/bol\.(.+)/'
            ),
            array(
                'name' => '<b>Coupang.com</b>',
                'parser' => 'CoupangParser',
                'valid_url' => '/coupang\.(.+)/'
            ),
            array(
                'name' => '<b>Emag.ro</b>, emag.bg, emag.hu',
                'parser' => 'EmagParser',
                'valid_url' => '/emag\.(.+)/'
            ),
            array(
                'name' => '<b>Manomano.com</b>, manomano.fr, manomano.es, manomano.it, manomano.co.uk, manomano.de',
                'parser' => 'ManomanoParser',
                'valid_url' => '/manomano\.(.+)/'
            ),
            array(
                'name' => '<b>Myntra.com</b>',
                'parser' => 'MyntraParser',
                'valid_url' => '/myntra\.(.+)/'
            ),
            array(
                'name' => '<b>Otto.de</b>',
                'parser' => 'OttoParser',
                'valid_url' => '/otto\.(.+)/'
            ),
            array(
                'name' => '<b>Overstock.com</b>',
                'parser' => 'OverstockParser',
                'valid_url' => '/overstock\.(.+)/'
            ),
            array(
                'name' => '<b>Tiki.vn</b>',
                'parser' => 'TikiParser',
                'valid_url' => '/tiki\.(.+)/'
            ),
            array(
                'name' => '<b>Takealot.com</b>',
                'parser' => 'TakealotParser',
                'valid_url' => '/takealot\.(.+)/'
            ),
            array(
                'name' => '<b>Spartoo.com</b>, spartoo.co.uk, spartoo.it, spartoo.es, spartoo.de, spartoo.be, de.spartoo.ch fr.spartoo.ch, it.spartoo.ch, spartoo.bg, spartoo.gr, spartoo.cz, spartoo.dk, spartoo.eu, spartoo.fi, spartoo.hu, spartoo.com.hr, spartoo.nl, spartoo.pl, spartoo.pt, spartoo.ro, spartoo.sk, spartoo.si, spartoo.se, spartoo.cn, fr.spartoo.ch',
                'parser' => 'SpartooParser',
                'valid_url' => '/spartoo\.(.+)/'
            ),
            array(
                'name' => '<b>Snapdeal.com</b>',
                'parser' => 'SnapdealParser',
                'valid_url' => '/snapdeal\.(.+)\/product\//'
            ),
            array(
                'name' => '<b>Shoptime.com.br</b>',
                'parser' => 'ShoptimeParser',
                'valid_url' => '/shoptime\.(.+)\/produto\//'
            ),
            array(
                'name' => '<b>Rueducommerce.fr</b>',
                'parser' => 'RueducommerceParser',
                'valid_url' => '/rueducommerce\.(.+)/'
            ),
            array(
                'name' => '<b>Reverb.com</b>',
                'parser' => 'ReverbParser',
                'valid_url' => '/reverb\.(.+)/'
            ),
            array(
                'name' => '<b>Pontofrio.com.br</b>',
                'parser' => 'PontofrioParser',
                'valid_url' => '/pontofrio\.(.+)/'
            ),
            array(
                'name' => '<b>Noon.com</b>',
                'parser' => 'NoonParser',
                'valid_url' => '/noon\.(.+)/'
            ),
            array(
                'name' => '<b>Netshoes.com.br</b>',
                'parser' => 'NetshoesParser',
                'valid_url' => '/netshoes\.(.+)/'
            ),					
            array(
                'name' => '<b>Kaufland.de</b>',
                'parser' => 'KauflandParser',
                'valid_url' => '/kaufland\.(.+)/'
            ),
            array(
                'name' => '<b>Jumia.com.ng</b>, jumia.dz, jumia.sn, jumia.com.eg, jumia.com.tn, jumia.com.gh, jumia.ug, jumia.ci, jumia.co.za, jumia.co.ke, jumia.ma',
                'parser' => 'JumiaParser',
                'valid_url' => '/jumia\.(.+)/'
            ),
            array(
                'name' => '<b>Joom.com</b>',
                'parser' => 'JoomParser',
                'valid_url' => '/joom\.(.+)/'
            ),
            array(
                'name' => '<b>Houzz.com</b>, houzz.co.uk',
                'parser' => 'HouzzParser',
                'valid_url' => '/houzz\.(.+)/'
            ),
            array(
                'name' => '<b>Gittigidiyor.com</b>',
                'parser' => 'GittigidiyorParser',
                'valid_url' => '/gittigidiyor\.(.+)/'
            ),
            array(
                'name' => '<b>G2a.com</b>',
                'parser' => 'G2aParser',
                'valid_url' => '/g2a\.(.+)/'
            ),
            array(
                'name' => '<b>Fruugo.com</b>, fruugo.ie, fruugoaustralia.com, fruugo.us, fruugo.at, fruugobahrain.com, fruugo.be, fruugo.ca, fruugochina.com, fruugo.cz, fruugo.dk, fruugo.fi, fruugo.fr, fruugo.gr, fruugoindia.com, fruugo.hu, fruugo.ie, fruugo.co.il, fruugo.it, fruugo.jp, fruugo.lu, fruugo.my, fruugo.nl, fruugo.co.nz, fruugo.no, fruugo.ph, fruugo.pl, fruugo.pt, fruugo.qa, fruugo.ro, fruugosaudiarabia.com, fruugo.sg, fruugo.sk, fruugo.co.za, fruugo.kr, fruugo.es, fruugo.se, fruugoschweiz.com, fruugo.com.tr, fruugo.ae, fruugo.co.uk',
                'parser' => 'FruugoParser',
                'valid_url' => '/fruugo(.*)\.(.+)/'
            ),
            array(
                'name' => '<b>Farfetch.com</b>',
                'parser' => 'FarfetchParser',
                'valid_url' => '/farfetch\.(.+)/'
            ),
            array(
                'name' => '<b>Falabella.com</b>',
                'parser' => 'FalabellaParser',
                'valid_url' => '/falabella\.(.+)/'
            ),
            array(
                'name' => '<b>Extra.com.br</b>',
                'parser' => 'ExtraParser',
                'valid_url' => '/extra\.(.+)/'
            ),
            array(
                'name' => '<b>Darty.com</b>',
                'parser' => 'DartyParser',
                'valid_url' => '/darty\.(.+)/'
            ),
            array(
                'name' => '<b>Dafiti.com.br</b>, Dafiti.com.ar, Dafiti.com.co, Dafiti.cl',
                'parser' => 'DafitiParser',
                'valid_url' => '/dafiti\.(.+)/'
            ),
            array(
                'name' => '<b>Conforama.fr</b>',
                'parser' => 'ConforamaParser',
                'valid_url' => '/conforama\.(.+)/'
            ),
            array(
                'name' => '<b>Casasbahia.com.br</b>',
                'parser' => 'CasasbahiaParser',
                'valid_url' => '/casasbahia\.(.+)/'
            ),
            array(
                'name' => '<b>Bukalapak.com</b>',
                'parser' => 'BukalapakParser',
                'valid_url' => '/bukalapak\.(.+)\/p\//'
            ),
            array(
                'name' => '<b>Blibli.com</b>',
                'parser' => 'BlibliParser',
                'valid_url' => '/blibli\.(.+)\/p\//'
            ),
            array(
                'name' => '<b>Bestbuy.com</b>, bestbuy.ca',
                'parser' => 'BestbuyParser',
                'valid_url' => '/bestbuy\.(.+)/'
            ),
            array(
                'name' => '<b>Barnesandnoble.com</b>',
                'parser' => 'BarnesandnobleParser',
                'valid_url' => '/barnesandnoble\.(.+)\/w\//'
            ),
            array(
                'name' => '<b>Abebooks.com</b>, abebooks.co.uk, abebooks.fr, abebooks.de, abebooks.it',
                'parser' => 'AbebooksParser',
                'valid_url' => '/abebooks\.(.+)/'
            ),
            array(
                'name' => '<b>Chewy.com</b>',
                'parser' => 'ChewyParser',
                'valid_url' => '/chewy\.(.+)/'
            ),
            array(
                'name' => '<b>Wehkamp.nl</b>',
                'parser' => 'WehkampParser',
                'valid_url' => '/wehkamp\.(.+)/'
            ),
            array(
                'name' => '<b>Zattini.com.br</b>',
                'parser' => 'ZattiniParser',
                'valid_url' => '/zattini\.(.+)/'
            ),
            array(
                'name' => '<b>Digitec.ch</b>',
                'parser' => 'DigitecParser',
                'valid_url' => '/digitec\.(.+)/'
            ),
            array(
                'name' => '<b>Costco.com</b>, costco.com.mx',
                'parser' => 'CostcoParser',
                'valid_url' => '/costco\.(.+)/'
            ),
            array(
                'name' => '<b>Samsclub.com</b>, sams.com.mx',
                'parser' => 'SamsclubParser',
                'valid_url' => '/sams(.+)\.(.+)/'
            ),
            array(
                'name' => '<b>Banggood.com</b>, usa.banggood.com, banggood.com, fr.banggood.com, pt.banggood.com, br.banggood.com, uk.banggood.com, au.banggood.com, nl.banggood.com, it.banggood.com, ru.banggood.com, es.banggood.com, pt.banggood.com, jp.banggood.com, ar.banggood.com, de.banggood.com, tr.banggood.com, hu.banggood.com, gr.banggood.com, banggood.in, pl.banggood.com',
                'parser' => 'BanggoodParser',
                'valid_url' => '/banggood\.(.+)/'
            ),
            array(
                'name' => '<b>Catch.com.au</b>',
                'parser' => 'CatchParser',
                'valid_url' => '/catch\.(.+)/'
            ),
            array(
                'name' => '<b>Sears.com, Kmart.com</b>',
                'parser' => 'SearsParser',
                'valid_url' => '/(kmart\.com|sears\.com)/'
            ),
            array(
                'name' => '<b>Zalora.com</b>, zalora.co.id, zalora.sg, zalora.com.ph, zalora.com.tw, zalora.com.hk, zalora.com.my',
                'parser' => 'ZaloraParser',
                'valid_url' => '/zalora\.(.+)/'
            ),
            array(
                'name' => '<b>Privalia.com</b>, es.privalia.com, it.privalia.com, br.privalia.com, <b>Veepee.nl</b>',
                'parser' => 'PrivaliaParser',
                'valid_url' => '/(privalia\.com|veepee\.nl)/'
            ),
            array(
                'name' => '<b>Grailed.com</b>',
                'parser' => 'GrailedParser',
                'valid_url' => '/grailed\.(.+)/'
            ),
            array(
                'name' => '<b>Zozo.jp</b>',
                'parser' => 'ZozoParser',
                'valid_url' => '/zozo\.(.+)/'
            )
        );
    }

    
    public static function getImportHostNames() {
        return implode(', ', array_column(self::getImportHosts(), 'name'));
    }

}

new Woo_EpicDrop_Hosts();