<?php

namespace CounterBundle\Entity;

use CounterBundle\Repository\CounterRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Tourze\DoctrineTimestampBundle\Traits\TimestampableAware;

#[ORM\Table(name: 'table_count', options: ['comment' => '计数器'])]
#[ORM\Entity(repositoryClass: CounterRepository::class)]
class Counter implements \Stringable
{
    use TimestampableAware;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER, options: ['comment' => 'ID'])]
    private int $id = 0;

    #[ORM\Column(length: 100, unique: true, options: ['comment' => '名称'])]
    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    private ?string $name = null;

    #[ORM\Column(options: ['comment' => '计数'])]
    #[Assert\PositiveOrZero]
    private int $count = 0;

    /**
     * @var array<string, mixed>|null
     */
    #[ORM\Column(type: Types::JSON, nullable: true, options: ['comment' => '上下文信息'])]
    #[Assert\Type(type: 'array')]
    private ?array $context = null;

    public function getId(): int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getCount(): int
    {
        return $this->count;
    }

    public function setCount(int $count): void
    {
        $this->count = $count;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getContext(): ?array
    {
        return $this->context;
    }

    /**
     * @param array<string, mixed>|null $context
     */
    public function setContext(?array $context): void
    {
        $this->context = $context;
    }

    public function __toString(): string
    {
        return sprintf('%s (%d)', $this->name ?? 'Unknown', $this->count);
    }
}
