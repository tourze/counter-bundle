<?php

namespace CounterBundle\Controller\Admin;

use App\Controller\AbstractCrudController;
use CounterBundle\Entity\Counter;

class CounterCounterCrudController extends AbstractCrudController
{

    public static function getEntityFqcn(): string
    {
        return Counter::class;
    }

    /*
    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id'),
            TextField::new('title'),
            TextEditorField::new('description'),
        ];
    }
    */
}
