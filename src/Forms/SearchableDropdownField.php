<?php

namespace SilverStripe\Forms;

use SilverStripe\Dev\Deprecation;
use SilverStripe\Forms\DropdownField;
use SilverStripe\ORM\DataList;
use SilverStripe\Forms\HasOneRelationFieldInterface;

class SearchableDropdownField extends DropdownField implements HasOneRelationFieldInterface
{
    use SearchableDropdownTrait;

    // This needs to be defined on the class, not the trait, otherwise there is a PHP error
    protected $schemaComponent = 'SearchableDropdownField';

    public function __construct(
        string $name,
        ?string $title = null,
        ?DataList $source = null,
        mixed $value = null,
        string $labelField = 'Title'
    ) {
        parent::__construct($name, $title, $source, $value);
        $this->setLabelField($labelField);
        $this->addExtraClass('ss-searchable-dropdown-field');
        $this->setHasEmptyDefault(true);
    }
}
