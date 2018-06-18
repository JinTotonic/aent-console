<?php

namespace TheAentMachine\Service;

use Opis\JsonSchema\ValidationError;
use Opis\JsonSchema\Validator;
use TheAentMachine\Service\Enum\EnvVariableTypeEnum;
use TheAentMachine\Service\Enum\VolumeTypeEnum;
use TheAentMachine\Service\Environment\EnvVariable;
use TheAentMachine\Service\Exception\ServiceException;
use TheAentMachine\Service\Volume\BindVolume;
use TheAentMachine\Service\Volume\NamedVolume;
use TheAentMachine\Service\Volume\TmpfsVolume;

class Service implements \JsonSerializable
{
    /** @var string */
    private $serviceName = '';
    /** @var string */
    private $image = '';
    /** @var int[] */
    private $internalPorts = [];
    /** @var string[] */
    private $dependsOn = [];
    /** @var mixed[] */
    private $ports = [];
    /** @var mixed[] */
    private $labels = [];
    /** @var mixed[] */
    private $environment = [];
    /** @var mixed[] */
    private $volumes = [];
    /** @var \stdClass */
    private $validatorSchema;

    /**
     * Service constructor.
     */
    public function __construct()
    {
        $this->validatorSchema = json_decode(file_get_contents(__DIR__ . '/ServiceJsonSchema.json'), false);
    }

    /**
     * @param mixed[] $payload
     * @return Service
     * @throws ServiceException
     */
    public static function parsePayload(array $payload): Service
    {
        $service = new Service();
        $service->checkValidity($payload);
        $service->serviceName = $payload['serviceName'] ?? '';
        $s = $payload['service'] ?? array();
        if (!empty($s)) {
            $service->image = $s['image'] ?? '';
            $service->internalPorts = $s['internalPorts'] ?? array();
            $service->dependsOn = $s['dependsOn'] ?? array();
            $service->ports = $s['ports'] ?? array();
            $service->labels = $s['labels'] ?? array();
            if (!empty($s['environment'])) {
                foreach ($s['environment'] as $key => $env) {
                    $service->addEnvVar($key, $env['value'], $env['type']);
                }
            }
            if (!empty($s['volumes'])) {
                foreach ($s['volumes'] as $vol) {
                    $service->addVolume($vol['type'], $vol['source'], $vol['target'] ?? '', $vol['readOnly'] ?? false);
                }
            }
        }
        return $service;
    }

    /**
     * Specify data which should be serialized to JSON
     * @link http://php.net/manual/en/jsonserializable.jsonserialize.php
     * @return array data which can be serialized by <b>json_encode</b>,
     * which is a value of any type other than a resource.
     * @since 5.4.0
     * @throws ServiceException
     */
    public function jsonSerialize(): array
    {
        $jsonSerializeMap = function (\JsonSerializable $obj): array {
            return $obj->jsonSerialize();
        };

        $array = self::arrayFilterRec(array(
            'serviceName' => $this->serviceName,
            'service' => [
                'image' => $this->image,
                'internalPorts' => $this->internalPorts,
                'dependsOn' => $this->dependsOn,
                'ports' => $this->ports,
                'labels' => $this->labels,
                'environment' => array_map($jsonSerializeMap, $this->environment),
                'volumes' => array_map($jsonSerializeMap, $this->volumes),
            ]
        ));

        $this->checkValidity($array);
        return $array;
    }

    /**
     * @param \stdClass|array|string $data
     * @return bool
     * @throws ServiceException
     */
    private function checkValidity($data): bool
    {
        if (\is_array($data)) {
            $data = json_decode(json_encode($data), false);
        }
        $validator = new Validator();
        $result = $validator->dataValidation($data, $this->validatorSchema);
        if (!$result->isValid()) {
            /** @var ValidationError $vError */
            $vError = $result->getFirstError();
            throw ServiceException::invalidServiceData($vError);
        }
        return $result->isValid();
    }

    /**
     * Delete all key/value pairs with empty value by recursively using array_filter
     * @param array $input
     * @return mixed[] array
     */
    private static function arrayFilterRec(array $input): array
    {
        foreach ($input as &$value) {
            if (\is_array($value)) {
                $value = self::arrayFilterRec($value);
            }
        }
        return array_filter($input);
    }


    /**
     * @return string
     */
    public function getServiceName(): string
    {
        return $this->serviceName;
    }

    /**
     * @return string
     */
    public function getImage(): string
    {
        return $this->image;
    }

    /**
     * @return int[]
     */
    public function getInternalPorts(): array
    {
        return $this->internalPorts;
    }

    /**
     * @return string[]
     */
    public function getDependsOn(): array
    {
        return $this->dependsOn;
    }

    /**
     * @return mixed[]
     */
    public function getPorts(): array
    {
        return $this->ports;
    }

    /**
     * @return mixed[]
     */
    public function getLabels(): array
    {
        return $this->labels;
    }

    /**
     * @return mixed[]
     */
    public function getEnvironment(): array
    {
        return $this->environment;
    }

    /**
     * @return mixed[]
     */
    public function getVolumes(): array
    {
        return $this->volumes;
    }

    /**
     * @param string $serviceName
     */
    public function setServiceName(string $serviceName): void
    {
        $this->serviceName = $serviceName;
    }

    /**
     * @param string $image
     */
    public function setImage(string $image): void
    {
        $this->image = $image;
    }

    /**
     * @param int[] $internalPorts
     */
    public function setInternalPorts(array $internalPorts): void
    {
        $this->internalPorts = $internalPorts;
    }

    /**
     * @param string[] $dependsOn
     */
    public function setDependsOn(array $dependsOn): void
    {
        $this->dependsOn = $dependsOn;
    }

    /**
     * @param int $internalPort
     */
    public function addInternalPort(int $internalPort): void
    {
        $this->internalPorts[] = $internalPort;
    }

    /**
     * @param string $dependsOn
     */
    public function addDependsOn(string $dependsOn): void
    {
        $this->dependsOn[] = $dependsOn;
    }

    /**
     * @param int $source
     * @param int $target
     */
    public function addPort(int $source, int $target): void
    {
        $this->ports[] = array(
            'source' => $source,
            'target' => $target,
        );
    }

    /**
     * @param string $key
     * @param string $value
     */
    public function addLabel(string $key, string $value): void
    {
        $this->labels[] = array(
            'key' => $key,
            'values' => $value,
        );
    }

    /**
     * @param string $key
     * @param string $value
     * @param string $type
     * @throws ServiceException
     */
    private function addEnvVar(string $key, string $value, string $type): void
    {
        switch ($type) {
            case EnvVariableTypeEnum::SHARED_ENV_VARIABLE:
                $this->addSharedEnvVariable($key, $value);
                break;
            case EnvVariableTypeEnum::SHARED_SECRET:
                $this->addSharedSecret($key, $value);
                break;
            case EnvVariableTypeEnum::IMAGE_ENV_VARIABLE:
                $this->addImageEnvVariable($key, $value);
                break;
            case EnvVariableTypeEnum::CONTAINER_ENV_VARIABLE:
                $this->addContainerEnvVariable($key, $value);
                break;
            default:
                throw ServiceException::unknownEnvVariableType($type);
        }
    }

    /**
     * @param string $key
     * @param string $value
     */
    public function addSharedEnvVariable(string $key, string $value): void
    {
        $this->environment[$key] = new EnvVariable($value, 'sharedEnvVariable');
    }

    /**
     * @param string $key
     * @param string $value
     */
    public function addSharedSecret(string $key, string $value): void
    {
        $this->environment[$key] = new EnvVariable($value, 'sharedSecret');
    }

    /**
     * @param string $key
     * @param string $value
     */
    public function addImageEnvVariable(string $key, string $value): void
    {
        $this->environment[$key] = new EnvVariable($value, 'imageEnvVariable');
    }

    /**
     * @param string $key
     * @param string $value
     */
    public function addContainerEnvVariable(string $key, string $value): void
    {
        $this->environment[$key] = new EnvVariable($value, 'containerEnvVariable');
    }

    /**
     * @param string $type
     * @param string $source
     * @param string $target
     * @param bool $readOnly
     * @throws ServiceException
     */
    private function addVolume(string $type, string $source, string $target = '', bool $readOnly = false): void
    {
        switch ($type) {
            case VolumeTypeEnum::NAMED_VOLUME:
                $this->addNamedVolume($source, $target, $readOnly);
                break;
            case VolumeTypeEnum::BIND_VOLUME:
                $this->addBindVolume($source, $target, $readOnly);
                break;
            case VolumeTypeEnum::TMPFS_VOLUME:
                $this->addTmpfsVolume($source);
                break;
            default:
                throw ServiceException::unknownVolumeType($type);
        }
    }

    /**
     * @param string $source
     * @param string $target
     * @param bool $readOnly
     */
    public function addNamedVolume(string $source, string $target, bool $readOnly = false): void
    {
        $this->volumes[] = new NamedVolume($source, $target, $readOnly);
    }

    /**
     * @param string $source
     * @param string $target
     * @param bool $readOnly
     */
    public function addBindVolume(string $source, string $target, bool $readOnly = false): void
    {
        $this->volumes[] = new BindVolume($source, $target, $readOnly);
    }

    /**
     * @param string $source
     */
    public function addTmpfsVolume(string $source): void
    {
        $this->volumes[] = new TmpfsVolume($source);
    }
}