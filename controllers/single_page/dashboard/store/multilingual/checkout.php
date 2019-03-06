<?php
namespace Concrete\Package\CommunityStore\Controller\SinglePage\Dashboard\Store\Multilingual;

use Concrete\Core\Routing\Redirect;
use Concrete\Core\Page\Controller\DashboardSitePageController;
use Concrete\Package\CommunityStore\Src\CommunityStore\Tax\Tax as StoreTax;
use Concrete\Package\CommunityStore\Src\CommunityStore\Discount\DiscountRule;
use Concrete\Package\CommunityStore\Src\CommunityStore\Multilingual\Translation;
use Concrete\Package\CommunityStore\Src\CommunityStore\Payment\Method as StorePaymentMethod;
use Concrete\Package\CommunityStore\Src\CommunityStore\Shipping\Method\ShippingMethod as StoreShippingMethod;

class Checkout extends DashboardSitePageController
{
    public function view()
    {
        $this->set("paymentMethods", StorePaymentMethod::getMethods());
        $this->set("shippingMethods", StoreShippingMethod::getMethods());
        $this->set("taxRates", StoreTax::getTaxRates());
        $this->set("discountRules", DiscountRule::getRules());

        $this->set('defaultLocale', $this->getLocales()['default']);
        $this->set('locales', $this->getLocales()['additional']);
        $this->set('pageTitle', t('Checkout Related Translations'));
    }

    private function getLocales()
    {
        $site = $this->getSite();
        $pages = \Concrete\Core\Multilingual\Page\Section\Section::getList($site);
        $localePages = ['additional' => []];
        $defaultSourceLocale = $site->getConfigRepository()->get('multilingual.default_source_locale');

        foreach ($pages as $p) {
            if ($defaultSourceLocale == $p->getLocale()) {
                $localePages['default'] = $p;
            } else {
                $localePages['additional'][] = $p;
            }
        }

        return $localePages;
    }

    public function save()
    {
        if ($this->request->request->all() && $this->token->validate('community_store')) {
            $translations = $this->request->request->get('translation');

            foreach ($translations as $entityType => $translationData) {
                foreach ($translationData as $paymentMethodID => $langs) {
                    foreach ($langs as $locale => $types) {
                        foreach ($types as $type => $items) {
                            foreach ($items as $key => $text) {
                                if ($text) {
                                    $qb = $this->entityManager->createQueryBuilder();
                                    $t = $qb->select('t')
                                        ->from('Concrete\Package\CommunityStore\Src\CommunityStore\Multilingual\Translation', 't')
                                        ->where('t.entityType = :type')->setParameter('type', $key)
                                        ->andWhere('t.locale = :locale')->setParameter('locale', $locale)
                                        ->andWhere('t.entityID = :entid')->setParameter('entid', $paymentMethodID)
                                        ->setMaxResults(1)->getQuery()->getResult();

                                    if (!empty($t)) {
                                        $t = $t[0];
                                    } else {
                                        $t = new Translation();
                                    }

                                    $t->setEntityID($paymentMethodID);
                                    $t->setEntityType($key);

                                    if ('text' == $type) {
                                        $t->setTranslatedText($text);
                                    } else {
                                        $t->setExtendedText($text);
                                    }
                                    $t->setLocale($locale);
                                    $t->save();
                                }
                            }
                        }
                    }
                }
            }
        }

        $this->flash('success', t('Checkout Translations Updated'));

        return Redirect::to('/dashboard/store/multilingual/checkout');
    }
}
