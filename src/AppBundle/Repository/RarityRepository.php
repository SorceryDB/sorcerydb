<?php

namespace AppBundle\Repository;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityRepository;

class RarityRepository extends EntityRepository
{
    public function __construct(EntityManager $entityManager)
    {
        parent::__construct($entityManager, $entityManager->getClassMetadata('AppBundle\Entity\Rarity'));
    }
}
