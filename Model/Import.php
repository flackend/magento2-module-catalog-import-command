<?php
namespace CedricBlondeau\CatalogImportCommand\Model;

use Magento\ImportExport\Model\Import as MagentoImport;
use Magento\ImportExport\Model\Import\ErrorProcessing\ProcessingErrorAggregatorInterface;
use Symfony\Component\Filesystem\Exception\FileNotFoundException;

/**
 * Class Import
 * @package CedricBlondeau\CatalogImportCommand\Model
 */
class Import
{
    /**
     * @var \Magento\ImportExport\Model\Import
     */
    protected $importModel;

    /**
     * @var \Magento\Framework\Filesystem\Directory\ReadFactory
     */
    protected $readFactory;

    /**
     * @var \Magento\ImportExport\Model\Import\Source\CsvFactory
     */
    protected $csvSourceFactory;

    /**
     * @var \Magento\Indexer\Model\Indexer\CollectionFactory
     */
    protected $indexerCollectionFactory;

    /**
     * @param \Magento\Eav\Model\Config $eavConfig
     * @param \Magento\ImportExport\Model\Import $importModel
     * @param \Magento\ImportExport\Model\Import\Source\CsvFactory $csvSourceFactory
     * @param \Magento\Indexer\Model\Indexer\CollectionFactory $indexerCollectionFactory
     * @param \Magento\Framework\Filesystem\Directory\ReadFactory $readFactory
     */
    public function __construct(
        \Magento\Eav\Model\Config $eavConfig,
        \Magento\ImportExport\Model\Import $importModel,
        \Magento\ImportExport\Model\Import\Source\CsvFactory $csvSourceFactory,
        \Magento\Indexer\Model\Indexer\CollectionFactory $indexerCollectionFactory,
        \Magento\Framework\Filesystem\Directory\ReadFactory $readFactory
    ) {
        $this->eavConfig = $eavConfig;
        $this->csvSourceFactory = $csvSourceFactory;
        $this->indexerCollectionFactory = $indexerCollectionFactory;
        $this->readFactory = $readFactory;
        $importModel->setData(
            [
                'entity' => 'catalog_product',
                'behavior' => MagentoImport::BEHAVIOR_APPEND,
                MagentoImport::FIELD_NAME_IMG_FILE_DIR => 'pub/media/catalog/product',
                MagentoImport::FIELD_NAME_VALIDATION_STRATEGY => ProcessingErrorAggregatorInterface::VALIDATION_STRATEGY_SKIP_ERRORS
            ]
        );
        $this->importModel = $importModel;
    }

    /**
     * @param $filePath Absolute file path to CSV file
     */
    public function setFile($filePath)
    {
        if (!file_exists($filePath)) {
            throw new FileNotFoundException();
        }
        // Hacky but quick fix for https://github.com/cedricblondeau/magento2-module-catalog-import-command/issues/1
        $pathInfo = pathinfo($filePath);
        $validate = $this->importModel->validateSource($this->csvSourceFactory->create(
            [
                'file' => $pathInfo['basename'],
                'directory' => $this->readFactory->create($pathInfo['dirname'])
            ]
        ));
        if (!$validate) {
            throw new \InvalidArgumentException();
        }
    }

    /**
     * @param $imagesPath
     */
    public function setImagesPath($imagesPath)
    {
        $this->importModel->setData(MagentoImport::FIELD_NAME_IMG_FILE_DIR, $imagesPath);
    }

    /**
     * @param $behavior
     */
    public function setBehavior($behavior)
    {
        if (in_array($behavior, array(
            MagentoImport::BEHAVIOR_APPEND,
            MagentoImport::BEHAVIOR_ADD_UPDATE,
            MagentoImport::BEHAVIOR_REPLACE,
            MagentoImport::BEHAVIOR_DELETE
        ))) {
            $this->importModel->setData('behavior', $behavior);
        }
    }

    /**
     * @return bool
     */
    public function execute()
    {
        $result = $this->importModel->importSource();
        if ($result) {
            $this->importModel->invalidateIndex();
        }
        return $result;
    }

    /**
     * @return string
     */
    public function getFormattedLogTrace()
    {
        // Yep, there is a typo here, see https://github.com/magento/magento2/pull/2771
        return $this->importModel->getFormatedLogTrace();
    }

    /**
     * @return MagentoImport\ErrorProcessing\ProcessingError[]
     */
    public function getErrors()
    {
        return $this->importModel->getErrorAggregator()->getAllErrors();
    }
}
