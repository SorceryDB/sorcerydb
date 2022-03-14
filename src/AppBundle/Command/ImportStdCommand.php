<?php

namespace AppBundle\Command;

use AppBundle\Behavior\Entity\NormalizableInterface;
use AppBundle\Entity\Card;
use AppBundle\Entity\Cycle;
use AppBundle\Entity\MwlCard;
use AppBundle\Entity\Prebuilt;
use AppBundle\Entity\Prebuiltslot;
use AppBundle\Entity\Rotation;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Filesystem\Filesystem;

class ImportStdCommand extends ContainerAwareCommand
{
    /** @var EntityManagerInterface $entityManager */
    private $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        parent::__construct();
        $this->entityManager = $entityManager;
    }

    /** @var OutputInterface $output */
    private $output;

    private $collections = [];

    protected function configure()
    {
        $this
            ->setName('app:import:std')
            ->setDescription('Import std data file in json format from a copy of https://github.com/zaroth/netrunner-cards-json')
            ->addArgument('path', InputArgument::REQUIRED, 'Path to the repository')
            ->addOption('force', 'f', InputOption::VALUE_NONE, "Yes to all questions");
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $path = $input->getArgument('path');
        $force = $input->getOption('force');

        $this->output = $output;

        $helper = $this->getHelper('question');

        // factions

        $output->writeln("Importing Factions...");
        $factionsFileInfo = $this->getFileInfo($path, 'factions.json');
        $imported = $this->importFactionsJsonFile($factionsFileInfo);
        if (!$force && count($imported)) {
            $question = new ConfirmationQuestion("Do you confirm? (Y/n) ", true);
            if (!$helper->ask($input, $output, $question)) {
                die();
            }
        }
        $this->entityManager->flush();
        $this->loadCollection('Faction');
        $output->writeln("Done.");

        // types

        $output->writeln("Importing Types...");
        $typesFileInfo = $this->getFileInfo($path, 'types.json');
        $imported = $this->importTypesJsonFile($typesFileInfo);
        if (!$force && count($imported)) {
            $question = new ConfirmationQuestion("Do you confirm? (Y/n) ", true);
            if (!$helper->ask($input, $output, $question)) {
                die();
            }
        }
        $this->entityManager->flush();
        $this->loadCollection('Type');
        $output->writeln("Done.");

        // cycles

        $output->writeln("Importing Cycles...");
        $cyclesFileInfo = $this->getFileInfo($path, 'cycles.json');
        $imported = $this->importCyclesJsonFile($cyclesFileInfo);
        if (!$force && count($imported)) {
            $question = new ConfirmationQuestion("Do you confirm? (Y/n) ", true);
            if (!$helper->ask($input, $output, $question)) {
                die();
            }
        }
        $this->entityManager->flush();
        $this->loadCollection('Cycle');
        $output->writeln("Done.");

        // packs

        $output->writeln("Importing Packs...");
        $packsFileInfo = $this->getFileInfo($path, 'packs.json');
        $imported = $this->importPacksJsonFile($packsFileInfo);
        if (!$force && count($imported)) {
            $question = new ConfirmationQuestion("Do you confirm? (Y/n) ", true);
            if (!$helper->ask($input, $output, $question)) {
                die();
            }
        }
        $this->entityManager->flush();
        $this->loadCollection('Pack');
        $output->writeln("Done.");

        // cards

        $output->writeln("Importing Cards...");
        $fileSystemIterator = $this->getFileSystemIterator($path);
        $imported = [];
        foreach ($fileSystemIterator as $fileinfo) {
            $imported = array_merge($imported, $this->importCardsJsonFile($fileinfo));
        }
        if (!$force && count($imported)) {
            $question = new ConfirmationQuestion("Do you confirm? (Y/n) ", true);
            if (!$helper->ask($input, $output, $question)) {
                die();
            }
        }
        $this->entityManager->flush();
        $output->writeln("Done.");

        // prebuilt

        $output->writeln("Importing Prebuilts...");
        $prebuiltFileInfo = $this->getFileInfo($path, 'prebuilts.json');
        $imported = $this->importPrebuiltJsonFile($prebuiltFileInfo);
        if (!$force && count($imported)) {
            $question = new ConfirmationQuestion("Do you confirm? (Y/n) ", true);
            if (!$helper->ask($input, $output, $question)) {
                die();
            }
        }
        $this->entityManager->flush();
        $output->writeln("Done.");

        // mwl

        $output->writeln("Importing MWL...");
        $mwlFileInfo = $this->getFileInfo($path, 'mwl.json');
        $imported = $this->importMwlJsonFile($mwlFileInfo);
        if (!$force && count($imported)) {
            $question = new ConfirmationQuestion("Do you confirm? (Y/n) ", true);
            if (!$helper->ask($input, $output, $question)) {
                die();
            }
        }
        $this->entityManager->flush();
        $output->writeln("Done.");

        // rotation

        $output->writeln("Importing Rotation...");
        $rotationFileInfo = $this->getFileInfo($path, 'rotations.json');
        $imported = $this->importRotationJsonFile($rotationFileInfo);
        $output->writeln("Imported: " . count($imported));
        if (!$force && count($imported)) {
            $question = new ConfirmationQuestion("Do you confirm? (Y/n) ", true);
            if (!$helper->ask($input, $output, $question)) {
                die();
            }
        }
        $this->entityManager->flush();
        $output->writeln("Done.");
    }

    protected function importSidesJsonFile(\SplFileInfo $fileinfo)
    {
        $result = [];

        $list = $this->getDataFromFile($fileinfo);
        foreach ($list as $data) {
            $side = $this->getEntityFromData('AppBundle\\Entity\\Side', $data, [
                'code',
                'name',
            ], [], []);
            if ($side !== null) {
                $result[] = $side;
                $this->entityManager->persist($side);
            }
        }

        return $result;
    }

    protected function importFactionsJsonFile(\SplFileInfo $fileinfo)
    {
        $result = [];

        $list = $this->getDataFromFile($fileinfo);
        foreach ($list as $data) {
            $faction = $this->getEntityFromData('AppBundle\\Entity\\Faction', $data, [
                'code',
                'name',
                'color',
                'is_mini',
            ], [], []);
            if ($faction) {
                $result[] = $faction;
                $this->entityManager->persist($faction);
            }
        }

        return $result;
    }

    protected function importTypesJsonFile(\SplFileInfo $fileinfo)
    {
        $result = [];

        $list = $this->getDataFromFile($fileinfo);
        foreach ($list as $data) {
            $type = $this->getEntityFromData('AppBundle\\Entity\\Type', $data, [
                'code',
                'name',
                'position',
                'is_subtype',
            ],[], []);
            if ($type) {
                $result[] = $type;
                $this->entityManager->persist($type);
            }
        }

        return $result;
    }

    protected function importCyclesJsonFile(\SplFileInfo $fileinfo)
    {
        $result = [];

        $cyclesData = $this->getDataFromFile($fileinfo);
        foreach ($cyclesData as $cycleData) {
            $cycle = $this->getEntityFromData('AppBundle\Entity\Cycle', $cycleData, [
                'code',
                'name',
                'position',
                'size',
                'rotated',
            ], [], []);
            if ($cycle) {
                $result[] = $cycle;
                $this->entityManager->persist($cycle);
            }
        }

        return $result;
    }

    protected function importPacksJsonFile(\SplFileInfo $fileinfo)
    {
        $result = [];

        $packsData = $this->getDataFromFile($fileinfo);
        foreach ($packsData as $packData) {
            $pack = $this->getEntityFromData('AppBundle\Entity\Pack', $packData, [
                'code',
                'name',
                'position',
                'size',
                'date_release',
                'ffg_id',
            ], [
                'cycle_code',
            ], []);
            if ($pack) {
                $result[] = $pack;
                $this->entityManager->persist($pack);
            }
        }

        return $result;
    }

    protected function importCardsJsonFile(\SplFileInfo $fileinfo)
    {
        $result = [];

        $code = $fileinfo->getBasename('.json');

        $pack = $this->entityManager->getRepository('AppBundle:Pack')->findOneBy(['code' => $code]);
        if (!$pack) {
            throw new \Exception("Unable to find Pack [$code]");
        }

        $cardsData = $this->getDataFromFile($fileinfo);
        foreach ($cardsData as $cardData) {
            $card = $this->getEntityFromData('AppBundle\Entity\Card', $cardData, [
                'code',
                'deck_limit',
                'position',
                'quantity',
                'stripped_title',
                'title',
                'uniqueness',
            ], [
                'faction_code',
                'pack_code',
                'type_code',
            ], [
                'illustrator',
                'flavor',
                'keywords',
                'text',
                'stripped_text',
                'cost',
                'faction_cost',
                'trash_cost',
            ]);
            if ($card) {
                $result[] = $card;
                $this->entityManager->persist($card);
            }
        }

        return $result;
    }

    protected function importPrebuiltJsonFile(\SplFileInfo $fileinfo)
    {
        $result = [];

        foreach ($this->getDataFromFile($fileinfo) as $prebuiltData) {
            $prebuilt = $this->getEntityFromData('AppBundle\Entity\Prebuilt', $prebuiltData, [
                'code',
                'name',
                'date_release',
                'position',
            ], [], []);
            if ($prebuilt instanceof Prebuilt) {
                $result[] = $prebuilt;
                $this->entityManager->persist($prebuilt);

                foreach ($prebuiltData['cards'] as $card_code => $quantity) {
                    $card = $this->entityManager->getRepository('AppBundle:Card')->findOneBy(['code' => $card_code]);
                    if (!$card instanceof Card) {
                        continue;
                    }
                    $prebuiltslot = new Prebuiltslot();
                    $prebuiltslot->setCard($card);
                    $prebuiltslot->setQuantity($quantity);
                    $prebuiltslot->setPrebuilt($prebuilt);
                    $this->entityManager->persist($prebuiltslot);

                    if ($card->getType()->getCode() === 'identity') {
                        $prebuilt->setIdentity($card);
                        $prebuilt->setFaction($card->getFaction());
                    }
                }
            }
        }

        return $result;
    }

    protected function importMwlJsonFile(\SplFileInfo $fileinfo)
    {
        $result = [];

        foreach ($this->getDataFromFile($fileinfo) as $mwlData) {
            $mwl = $this->getEntityFromData('AppBundle\Entity\Mwl', $mwlData, [
                'code',
                'name',
                'date_start',
                'cards',
            ], [], []);
            if ($mwl) {
                $result[] = $mwl;
                $this->entityManager->getConnection()->beginTransaction();
                try {
                    $this->entityManager->persist($mwl);
                    $this->output->writeln("Wrote MWL with name <info>" . $mwl->getName() . "</info>");
                    $q = $this->entityManager->createQuery('delete from AppBundle:MwlCard mc where mc.mwl_id = ?1');
                    $q->setParameter(1, $mwl->getId());
                    $q->execute();

                    foreach ($mwlData['cards'] as $card_code => $mwl_entry) {
                        $card = $this->entityManager->getRepository('AppBundle:Card')->findOneBy(['code' => $card_code]);
                        if (!$card instanceof Card) {
                            $this->output->writeln("<error>Couldn't load card with code $card_code</error>");
                            continue;
                        }
                        $mwl_value = '';
                        $mwl_card = new MwlCard();
                        $mwl_card->setMwl($mwl);
                        $mwl_card->setCard($card);
                        if (array_key_exists('global_penalty', $mwl_entry)) {
                            $mwl_card->setGlobalPenalty($mwl_entry['global_penalty']);
                            $mwl_value = 'global penalty: ' . $mwl_entry['global_penalty'];
                        }
                        if (array_key_exists('universal_faction_cost', $mwl_entry)) {
                            $mwl_card->setUniversalFactionCost($mwl_entry['universal_faction_cost']);
                            $mwl_value = 'universal faction cost: ' . $mwl_entry['universal_faction_cost'];
                        }
                        if (array_key_exists('is_restricted', $mwl_entry)) {
                            $mwl_card->setIsRestricted($mwl_entry['is_restricted']);
                            $mwl_value = 'is_restricted: true';
                        }
                        if (array_key_exists('deck_limit', $mwl_entry)) {
                            $mwl_card->setIsBanned(true);
                            $mwl_value = 'is_banned: true';
                        }
                        $this->entityManager->persist($mwl_card);
                        $this->output->writeln("Wrote MWL entry for MWL <info>" . $mwl->getName() . "</info> and card <info>" . $card->getTitle() . " (" . $card->getCode() . ")</info>, mwl value: <info>$mwl_value</info>");
                    }
                    $this->entityManager->persist($mwl);
                    $this->entityManager->flush();
                    $this->entityManager->getConnection()->commit();
                } catch (Exception $e) {
                    $this->entityManager->getConnection()->rollBack();
                    throw $e;
                }
            }
        }

        return $result;
    }

    protected function importRotationJsonFile(\SplFileInfo $fileinfo)
    {
        $result = [];

        $data = $this->getDataFromFile($fileinfo);
        foreach ($data as $rotationData) {
            /** @var Rotation $rotation */
            $rotation = $this->getEntityFromData('AppBundle\Entity\Rotation', $rotationData, [
                'code',
                'name',
                'date_start',
            ], [], []);
            if ($rotation) {
                $result[] = $rotation;
                foreach ($rotationData['rotated'] as $cycle_code) {
                    $cycle = $this->entityManager->getRepository('AppBundle:Cycle')->findOneBy(['code' => $cycle_code]);
                    if (!$cycle instanceof Cycle) {
                        continue;
                    }
                    $rotation->addCycle($cycle);
                }
                $this->entityManager->persist($rotation);
            }
        }

        return $result;
    }

    protected function copyFieldValueToEntity($entity, string $entityName, string $fieldName, $newJsonValue)
    {
        $metadata = $this->entityManager->getClassMetadata($entityName);
        $type = $metadata->fieldMappings[$fieldName]['type'];

        // new value, by default what json gave us is the correct typed value
        $newTypedValue = $newJsonValue;

        // current value, by default the json, serialized value is the same as what's in the entity
        $getter = 'get' . ucfirst($fieldName);
        $currentJsonValue = $currentTypedValue = $entity->$getter();

        // if the field is a data, the default assumptions above are wrong
        if (in_array($type, ['date', 'datetime'])) {
            if ($newJsonValue !== null) {
                $newTypedValue = new \DateTime($newJsonValue);
            }
            if ($currentTypedValue instanceof \DateTime) {
                switch ($type) {
                    case 'date':
                        {
                            $currentJsonValue = $currentTypedValue->format('Y-m-d');
                            break;
                        }
                    case 'datetime':
                        {
                            $currentJsonValue = $currentTypedValue->format('Y-m-d H:i:s');
                        }
                }
            }
        }

        // Special case faction cost for agenda and identity cards which will always have null values returned as 0.
        if ($entityName == 'AppBundle\Entity\Card' && $fieldName == 'factionCost' && ($entity->getType()->getCode() == 'identity' || $entity->getType()->getCode() == 'agenda')) {
            return;
        }

        if ($currentJsonValue !== $newJsonValue) {
            $this->output->writeln("Changing the <info>$fieldName</info> of <info>" . $entity . "</info>");

            $setter = 'set' . ucfirst($fieldName);
            $entity->$setter($newTypedValue);
        }
    }

    protected function copyKeyToEntity($entity, string $entityName, array $data, string $key, bool $isMandatory = true)
    {
        $metadata = $this->entityManager->getClassMetadata($entityName);

        if (!key_exists($key, $data)) {
            if ($isMandatory) {
                throw new \Exception("Missing key [$key] in " . json_encode($data));
            } else {
                $data[$key] = null;
            }
        }
        $value = $data[$key];

        if (!key_exists($key, $metadata->fieldNames)) {
            throw new \Exception("Invalid key [$key] in " . json_encode($data));
        }
        $fieldName = $metadata->fieldNames[$key];

        $this->copyFieldValueToEntity($entity, $entityName, $fieldName, $value);
    }

    /**
     *
     * @param string $entityName
     * @param array $data
     * @param array $mandatoryKeys
     * @param array $foreignKeys
     * @param array $optionalKeys
     * @throws \Exception
     * @return object|null
     */
    protected function getEntityFromData(string $entityName, array $data, array $mandatoryKeys, array $foreignKeys, array $optionalKeys)
    {
        if (!key_exists('code', $data)) {
            throw new \Exception("Missing key [code] in " . json_encode($data));
        }

        $entity = $this->entityManager->getRepository($entityName)->findOneBy(['code' => $data['code']]);
        if (!$entity) {
            $entity = new $entityName();
        }
        if (!$entity instanceof NormalizableInterface) {
            throw new \Exception('Entity non-normalizable');
        }
        $orig = $entity->normalize();

        foreach ($mandatoryKeys as $key) {
            $this->copyKeyToEntity($entity, $entityName, $data, $key, true);
        }

        foreach ($foreignKeys as $key) {
            $foreignEntityShortName = ucfirst(str_replace('_code', '', $key));

            if (!key_exists($key, $data)) {
                throw new \Exception("Missing key [$key] in " . json_encode($data));
            }

            $foreignCode = $data[$key];
            if ($foreignCode === null) {
                continue;
            }
            if (!key_exists($foreignEntityShortName, $this->collections)) {
                throw new \Exception("No collection for [$foreignEntityShortName] in " . json_encode($data));
            }
            if (!key_exists($foreignCode, $this->collections[$foreignEntityShortName])) {
                throw new \Exception("Invalid code [$foreignCode] for key [$key] in " . json_encode($data));
            }
            /** @var NormalizableInterface $foreignEntity */
            $foreignEntity = $this->collections[$foreignEntityShortName][$foreignCode];

            $getter = 'get' . $foreignEntityShortName;
            if (!$entity->$getter() || $entity->$getter()->getId() !== $foreignEntity->getId()) {
                $this->output->writeln("Changing the <info>$key</info> of <info>" . $entity . "</info>");
                $setter = 'set' . $foreignEntityShortName;
                $entity->$setter($foreignEntity);
            }
        }

        foreach ($optionalKeys as $key) {
            $this->copyKeyToEntity($entity, $entityName, $data, $key, false);
        }

        // special case for Card
        if ($entity instanceof Card) {
            // calling a function whose name depends on the type_code
            $functionName = 'import' . $entity->getType()->getName() . 'Data';
            $this->$functionName($entity, $data);
        }

        if (!$entity instanceof NormalizableInterface) {
            throw new \Exception('Entity non-normalizable');
        }
        $newer = $entity->normalize();

        // special case for Mwl
        if ($entityName === 'AppBundle\Entity\Mwl') {
            $newer['cards'] = $data['cards'];
            unset($newer['active']);
            unset($orig['active']);
        }

        if (!$this->deepArrayEquality($newer, $orig)) {
            return $entity;
        }

        // Special handling for rotation.
        // The code above already catches new rotation entries, but if the only
        // difference is in the cycles already in the db for an existing entry,
        // we need to check that manually.  If those are the same, just let the
        // existing handling do its work.
        if ($entityName === 'AppBundle\Entity\Rotation') {
            $json_cycles = $data['rotated'];
            sort($json_cycles);
            $db_cycles = array();
            foreach ($entity->GetRotated() as $c) {
                array_push($db_cycles, $c->GetCode());
            }
            sort($db_cycles);
            if ($json_cycles != $db_cycles) {
                $this->output->writeln("Cycles don't match for rotation <info>" . $entity->GetName() . "</info>, so updating it.");
                return $entity;
            }
        }

        return null;
    }

    /**
     * Encodes an array in JSON in such a way that two identical arrays have the same representation
     * @param array $data
     * @return string
     */
    protected function uniquelyEncodeJson(array $data)
    {
        ksort($data);

        return json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * @param array $array1
     * @param array $array2
     * @return bool
     */
    protected function deepArrayEquality(array $array1, array $array2)
    {
        return $this->uniquelyEncodeJson($array1) === $this->uniquelyEncodeJson($array2);
    }

    protected function importAgendaData(Card $card, array $data)
    {
        $mandatoryKeys = [
            'advancement_cost',
            'agenda_points',
        ];

        foreach ($mandatoryKeys as $key) {
            $this->copyKeyToEntity($card, 'AppBundle\Entity\Card', $data, $key, true);
        }
    }

    protected function importAssetData(Card $card, array $data)
    {
        $mandatoryKeys = [
            'cost',
            'faction_cost',
            'trash_cost',
        ];

        foreach ($mandatoryKeys as $key) {
            $this->copyKeyToEntity($card, 'AppBundle\Entity\Card', $data, $key, true);
        }
    }

    protected function importAvatarData(Card $card, array $data)
    {
        $mandatoryKeys = [
            'health',
            'power',
        ];

        foreach ($mandatoryKeys as $key) {
            $this->copyKeyToEntity($card, 'AppBundle\Entity\Card', $data, $key, true);
        }
    }

    protected function importEventData(Card $card, array $data)
    {
        $mandatoryKeys = [
            'cost',
            'faction_cost',
        ];

        foreach ($mandatoryKeys as $key) {
            $this->copyKeyToEntity($card, 'AppBundle\Entity\Card', $data, $key, true);
        }
    }

    protected function importHardwareData(Card $card, array $data)
    {
        $mandatoryKeys = [
            'cost',
            'faction_cost',
        ];

        foreach ($mandatoryKeys as $key) {
            $this->copyKeyToEntity($card, 'AppBundle\Entity\Card', $data, $key, true);
        }
    }

    protected function importICEData(Card $card, array $data)
    {
        $mandatoryKeys = [
            'cost',
            'faction_cost',
            'strength',
        ];

        foreach ($mandatoryKeys as $key) {
            $this->copyKeyToEntity($card, 'AppBundle\Entity\Card', $data, $key, true);
        }
    }

    protected function importIdentityData(Card $card, array $data)
    {
        $mandatoryKeys = [
            'minimum_deck_size',
        ];

        if ($card->getPack()->getCode() !== 'draft') {
            $mandatoryKeys[] = 'influence_limit';
        }

        foreach ($mandatoryKeys as $key) {
            $this->copyKeyToEntity($card, 'AppBundle\Entity\Card', $data, $key, true);
        }
    }

    protected function importOperationData(Card $card, array $data)
    {
        $mandatoryKeys = [
            'cost',
            'faction_cost',
        ];

        foreach ($mandatoryKeys as $key) {
            $this->copyKeyToEntity($card, 'AppBundle\Entity\Card', $data, $key, true);
        }
    }

    protected function importProgramData(Card $card, array $data)
    {
        $mandatoryKeys = [
            'cost',
            'memory_cost',
            'faction_cost',
        ];

        if (strstr($card->getKeywords(), 'Icebreaker') !== false) {
            $mandatoryKeys[] = 'strength';
        }

        foreach ($mandatoryKeys as $key) {
            $this->copyKeyToEntity($card, 'AppBundle\Entity\Card', $data, $key, true);
        }
    }

    protected function importResourceData(Card $card, array $data)
    {
        $mandatoryKeys = [
            'cost',
            'faction_cost',
        ];

        foreach ($mandatoryKeys as $key) {
            $this->copyKeyToEntity($card, 'AppBundle\Entity\Card', $data, $key, true);
        }
    }

    protected function importUpgradeData(Card $card, array $data)
    {
        $mandatoryKeys = [
            'cost',
            'faction_cost',
            'trash_cost',
        ];

        foreach ($mandatoryKeys as $key) {
            $this->copyKeyToEntity($card, 'AppBundle\Entity\Card', $data, $key, true);
        }
    }

    protected function getDataFromFile(\SplFileInfo $fileinfo)
    {
        $file = $fileinfo->openFile('r');
        $file->setFlags(\SplFileObject::SKIP_EMPTY | \SplFileObject::DROP_NEW_LINE);

        $lines = [];
        foreach ($file as $line) {
            if ($line !== false) {
                $lines[] = $line;
            }
        }
        $content = implode('', $lines);

        $data = json_decode($content, true);

        if ($data === null) {
            throw new \Exception("File [" . $fileinfo->getPathname() . "] contains incorrect JSON (error code " . json_last_error() . ")");
        }

        return $data;
    }

    protected function getFileInfo(string $path, string $filename)
    {
        $fs = new Filesystem();

        if (!$fs->exists($path)) {
            throw new \Exception("No repository found at [$path]");
        }

        $filepath = "$path/$filename";

        if (!$fs->exists($filepath)) {
            throw new \Exception("No $filename file found at [$path]");
        }

        return new \SplFileInfo($filepath);
    }

    protected function getFileSystemIterator(string $path)
    {
        $fs = new Filesystem();

        if (!$fs->exists($path)) {
            throw new \Exception("No repository found at [$path]");
        }

        $directory = 'pack';

        if (!$fs->exists("$path/$directory")) {
            throw new \Exception("No '$directory' directory found at [$path]");
        }

        $iterator = new \GlobIterator("$path/$directory/*.json");

        if (!$iterator->count()) {
            throw new \Exception("No json file found at [$path/set]");
        }

        return $iterator;
    }

    protected function loadCollection(string $entityShortName)
    {
        $this->collections[$entityShortName] = [];

        $entities = $this->entityManager->getRepository('AppBundle:' . $entityShortName)->findAll();

        foreach ($entities as $entity) {
            $this->collections[$entityShortName][$entity->getCode()] = $entity;
        }
    }
}
