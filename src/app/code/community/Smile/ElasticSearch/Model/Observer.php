<?php
/**
 * Search observer
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade Smile Searchandising Suite to newer
 * versions in the future.
 *
 * This work is a fork of Johann Reinke <johann@bubblecode.net> previous module
 * available at https://github.com/jreinke/magento-elasticsearch
 *
 * @category  Smile
 * @package   Smile_ElasticSearch
 * @author    Aurelien FOUCRET <aurelien.foucret@smile.fr>
 * @copyright 2013 Smile
 * @license   Apache License Version 2.0
 */
class Smile_ElasticSearch_Model_Observer
{
    /**
     * Adds search weight parameter in attribute form.
     *
     * @param Varien_Event_Observer $observer Event data
     *
     * @return Smile_ElasticSearch_Model_Observer Self Reference
     */
    public function eavAttributeEditFormInit(Varien_Event_Observer $observer)
    {
        /** @var $attribute Mage_Catalog_Model_Resource_Eav_Attribute */
        $attribute = $observer->getEvent()->getAttribute();
        $form = $observer->getEvent()->getForm();
        $fieldset = $form->getElement('front_fieldset');

        $fieldset->addField(
            'search_weight',
            'select', array(
                'name' => 'search_weight',
                'label' => Mage::helper('smile_elasticsearch')->__('Search Weight'),
                'values' => array(
                    1 => 1,
                    2 => 2,
                    3 => 3,
                    4 => 4,
                    5 => 5
                ),
                ),
            'is_searchable'
        );

        if ($attribute->getAttributeCode() == 'name') {
            $form->getElement('is_searchable')->setDisabled(1);
        }

        return $this;
    }

    /**
     * Requires catalog search indexation.
     *
     * @param Varien_Event_Observer $observer Event data
     *
     * @return Smile_ElasticSearch_Model_Observer Self Reference
     */
    public function requireCatalogsearchReindex(Varien_Event_Observer $observer)
    {
        if (Mage::helper('smile_elasticsearch')->isActiveEngine()) {
            /** @var $attribute Mage_Catalog_Model_Resource_Eav_Attribute */
            $attribute = $observer->getEvent()->getAttribute();
            if ($attribute->getData('search_weight') != $attribute->getOrigData('search_weight')) {
                Mage::getSingleton('index/indexer')->getProcessByCode('catalogsearch_fulltext')
                    ->changeStatus(Mage_Index_Model_Process::STATUS_REQUIRE_REINDEX);
            }
        }

        return $this;
    }

    /**
     * Reset search engine if it is enabled for catalog navigation
     *
     * @param Varien_Event_Observer $observer Event data
     *
     * @return Smile_ElasticSearch_Model_Observer Self Reference
     */
    public function resetCurrentCatalogLayer(Varien_Event_Observer $observer)
    {
        if (Mage::helper('smile_elasticsearch')->isActiveEngine()) {
            Mage::register('current_layer', Mage::getSingleton('smile_elasticsearch/catalog_layer'));
        }

        return $this;
    }

    /**
     * Reset search engine if it is enabled for search navigation
     *
     * @param Varien_Event_Observer $observer Event data
     *
     * @return Smile_ElasticSearch_Model_Observer Self Reference
     */
    public function resetCurrentSearchLayer(Varien_Event_Observer $observer)
    {
        if (Mage::helper('smile_elasticsearch')->isActiveEngine()) {
            Mage::register('current_layer', Mage::getSingleton('smile_elasticsearch/catalogsearch_layer'));
        }

        return $this;
    }

    /**
      * Retrieve Fulltext Search instance
      *
      * @return Mage_CatalogSearch_Model_Fulltext
      */
    protected function _getIndexer()
    {
        return Mage::getSingleton('catalogsearch/fulltext');
    }

    /**
      * Fix category product indexing when product list changes for a category
      *
      * @param Varien_Event_Observer $observer Event data
      *
      * @return Smile_ElasticSearch_Model_Observer
      */
    public function reindexCategoryProduct(Varien_Event_Observer $observer)
    {
        $category = $observer->getEvent()->getCategory();
        $productIds = $category->getProductCollection()->getAllIds();
        $this->_getIndexer()->resetSearchResults();
        $currentIndex = Mage::helper('catalogsearch')->getEngine()->getCurrentIndex();
        $currentIndex->getMapping('product')->rebuildIndex(null, $productIds);
        Mage::dispatchEvent('smile_search_engine_reindex_category', array('category' => $category));
        return $this;
    }

    /**
     * Process shell reindex catalog full text refresh event
     *
     * @param Varien_Event_Observer $observer Event to observe.
     *
     * @return Enterprise_CatalogSearch_Model_Observer
     */
    public function processShellFulltextReindexEvent(Varien_Event_Observer $observer)
    {
        $helper = Mage::helper('smile_elasticsearch');
        if ($helper->isEnterpriseSupportEnabled() == true && $helper->isActiveEngine() == false) {
            $client = $this->_factory->getModel('enterprise_mview/client', array(array('factory' => $this->_factory)));
            $client->init('catalogsearch_fulltext');
            $client->execute('enterprise_catalogsearch/index_action_fulltext_refresh');
        }
        return $this;
    }
}

