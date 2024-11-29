<?php

declare(strict_types=1);

namespace Shared\Infrastructure\FileSystem;

use Easy\Container\Attributes\Inject;
use League\Flysystem\Visibility;
use Override;
use Shared\Infrastructure\FileSystem\Adapters\CdnAdapterInterface;
use Shared\Infrastructure\FileSystem\Adapters\LocalCdnAdapter;
use Shared\Infrastructure\FileSystem\Exceptions\AdapterNotFoundException;

class Cdn extends FileSystem implements CdnInterface
{
    private CdnAdapterInterface $adapter;

    public function __construct(
        private CdnAdapterCollectionInterface $collection,

        #[Inject('option.cdn.adapter')]
        private ?string $adapterLookupKey = LocalCdnAdapter::LOOKUP_KEY
    ) {
        if (is_null($this->adapterLookupKey)) {
            $this->adapterLookupKey = LocalCdnAdapter::LOOKUP_KEY;
        }

        try {
            $this->adapter = $this->collection->get($this->adapterLookupKey);
        } catch (AdapterNotFoundException $th) {
            if ($adapterLookupKey == LocalCdnAdapter::LOOKUP_KEY) {
                throw $th;
            }

            $this->adapter = $this->collection->get(LocalCdnAdapter::LOOKUP_KEY);
            $this->adapterLookupKey = LocalCdnAdapter::LOOKUP_KEY;
        }

        parent::__construct($this->adapter);
    }

    #[Override]
    public function getUrl(string $path): ?string
    {
        return $this->adapter->getUrl($path);
    }

    #[Override]
    public function getAdapterLookupKey(): string
    {
        return $this->adapterLookupKey;
    }

    #[Override]
    public function write(
        string $location,
        string $contents,
        array $config = []
    ): void {
        if (!isset($config['visibility'])) {
            $config['visibility'] = Visibility::PUBLIC;
        }

        parent::write($location, $contents, $config);
    }
}
