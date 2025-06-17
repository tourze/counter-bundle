<?php

namespace CounterBundle\Entity;

use CounterBundle\Repository\CounterRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Tourze\DoctrineTimestampBundle\Traits\TimestampableAware;

#[ORM\Table(name: 'table_count', options: ['comment' => '计数器'])]
#[ORM\Entity(repositoryClass: CounterRepository::class)]
class Counter
{
    use TimestampableAware;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER, options: ['comment' => 'ID'])]
    private ?int $id = 0;

    #[ORM\Column(length: 100, unique: true, options: ['comment' => '名称'])]
    private ?string $name = null;

    #[ORM\Column(options: ['comment' => '计数'])]
    private ?int $count = 0;

    #[ORM\Column(type: Types::JSON, nullable: true, options: ['comment' => '上下文信息'])]
    private ?array $context = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getCount(): ?int
    {
        return $this->count;
    }

    public function setCount(int $count): static
    {
        $this->count = $count;

        return $this;
    }

    public function getContext(): ?array
    {
        return $this->context;
    }

    public function setContext(?array $context): static
    {
        $this->context = $context;

        return $this;
    }


}
