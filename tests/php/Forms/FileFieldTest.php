<?php

namespace SilverStripe\Forms\Tests;

use ReflectionMethod;
use SilverStripe\Core\Convert;
use SilverStripe\Assets\Upload_Validator;
use SilverStripe\Dev\FunctionalTest;
use SilverStripe\Control\Controller;
use SilverStripe\Forms\FileField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\RequiredFields;

class FileFieldTest extends FunctionalTest
{
    /**
     * Test a valid upload of a required file in a form. Error is set to 0, as the upload went well
     *
     */
    public function testUploadRequiredFile()
    {
        $form = new Form(
            Controller::curr(),
            'Form',
            new FieldList(
                $fileField = new FileField('cv', 'Upload your CV')
            ),
            new FieldList()
        );
        $fileFieldValue = [
            'name' => 'aCV.txt',
            'type' => 'application/octet-stream',
            'tmp_name' => '/private/var/tmp/phpzTQbqP',
            'error' => 0,
            'size' => 3471
        ];
        $fileField->setValue($fileFieldValue);

        $this->assertTrue($form->validate()->isValid());
    }

    /**
     * Test that FileField::validate() is run on FileFields with both single and multi-file syntax
     * By default FileField::validate() will return true early if the $_FILES super-global does not contain the
     * corresponding FileField::name. This early return means the files was not fully run through FileField::validate()
     * So for this test we create an invalid file upload on purpose and test that false was returned which means that
     * the file was run through FileField::validate() function
     */
    public function testMultiFileSyntaxUploadIsValidated()
    {
        $names = [
            'single_file_syntax',
            'multi_file_syntax_a[]',
            'multi_file_syntax_b[0]',
            'multi_file_syntax_c[key]'
        ];
        foreach ($names as $name) {
            $form = new Form(
                Controller::curr(),
                'Form',
                new FieldList($fileField = new FileField($name, 'My desc')),
                new FieldList()
            );
            $fileData = $this->createInvalidUploadedFileData($name, "FileFieldTest.txt");
            // FileFields with multi_file_syntax[] files will appear in the $_FILES super-global
            // with the [] brackets trimmed e.g. $_FILES[multi_file_syntax]
            $_FILES = [preg_replace('#\[(.*?)\]#', '', $name) => $fileData];
            $fileField->setValue($fileData);
            $validator = $form->getValidator();
            $isValid = $fileField->validate($validator);
            $this->assertFalse($isValid, "$name was run through the validate() function");
        }
    }

    protected function createInvalidUploadedFileData($name, $tmpFileName): array
    {
        $tmpFilePath = TEMP_PATH . DIRECTORY_SEPARATOR . $tmpFileName;

        // multi_file_syntax
        if (strpos($name ?? '', '[') !== false) {
            $key = 0;
            if (preg_match('#\[(.+?)\]#', $name ?? '', $m)) {
                $key = $m[1];
            }
            return [
                'name' => [$key => $tmpFileName],
                'type' => [$key => 'text/plaintext'],
                'size' => [$key => 0],
                'tmp_name' => [$key => $tmpFilePath],
                'error' => [$key => UPLOAD_ERR_NO_FILE],
            ];
        }
        // single_file_syntax
        return [
            'name' => $tmpFileName,
            'type' => 'text/plaintext',
            'size' => 0,
            'tmp_name' => $tmpFilePath,
            'error' => UPLOAD_ERR_NO_FILE,
        ];
    }

    public function testGetAcceptFileTypes()
    {
        $field = new FileField('image', 'Image');
        $field->setAllowedExtensions('jpg', 'png');

        $method = new ReflectionMethod($field, 'getAcceptFileTypes');
        $method->setAccessible(true);
        $allowed = $method->invoke($field);

        $expected = ['.jpg', '.png', 'image/jpeg', 'image/png'];
        foreach ($expected as $extensionOrMime) {
            $this->assertContains($extensionOrMime, $allowed);
        }
    }

    /**
     * Test different scenarii for a failed upload : an error occurred, no files where provided
     */
    public function testUploadMissingRequiredFile()
    {
        $form = new Form(
            Controller::curr(),
            'Form',
            new FieldList(
                $fileField = new FileField('cv', 'Upload your CV')
            ),
            new FieldList(),
            new RequiredFields('cv')
        );
        // All fields are filled but for some reason an error occurred when uploading the file => fails
        $fileFieldValue = [
            'name' => 'aCV.txt',
            'type' => 'application/octet-stream',
            'tmp_name' => '/private/var/tmp/phpzTQbqP',
            'error' => 1,
            'size' => 3471
        ];
        $fileField->setValue($fileFieldValue);

        $this->assertFalse(
            $form->validate()->isValid(),
            'An error occurred when uploading a file, but the validator returned true'
        );

        // We pass an empty set of parameters for the uploaded file => fails
        $fileFieldValue = [];
        $fileField->setValue($fileFieldValue);

        $this->assertFalse(
            $form->validate()->isValid(),
            'An empty array was passed as parameter for an uploaded file, but the validator returned true'
        );

        // We pass an null value for the uploaded file => fails
        $fileFieldValue = null;
        $fileField->setValue($fileFieldValue);

        $this->assertFalse(
            $form->validate()->isValid(),
            'A null value was passed as parameter for an uploaded file, but the validator returned true'
        );
    }
  
    /**
     * Test the file size validation will use the PHP max size setting if
     * no config for the Upload_Validator::default_max_file_size has been defined
     */
    public function testWeWillDefaultToPHPMaxUploadSizingForValidation()
    {
        // These 3 lines are how SilverStripe works out the default max upload size as defined in Upload_Validator
        $phpMaxUpload = Convert::memstring2bytes(ini_get('upload_max_filesize'));
        $maxPost = Convert::memstring2bytes(ini_get('post_max_size'));
        $defaultUploadSize = min($phpMaxUpload, $maxPost);

        $fileField = new FileField('DemoField');

        $this->assertEquals($defaultUploadSize, $fileField->getValidator()->getAllowedMaxFileSize('jpg'));
        $this->assertEquals($defaultUploadSize, $fileField->getValidator()->getAllowedMaxFileSize('png'));
    }

    /**
     * Test the file size validation will use the default_max_file_size validation config if defined
     */
    public function testWeUseConfigForSizingIfDefined()
    {
        $configMaxFileSizes = [
            'jpg' => $jpgSize = '2m',
            '*' => $defaultSize = '1m',
        ];

        Upload_Validator::config()->set('default_max_file_size', $configMaxFileSizes);

        $fileField = new FileField('DemoField');

        $this->assertEquals(Convert::memstring2bytes($jpgSize), $fileField->getValidator()->getAllowedMaxFileSize('jpg'));

        // PNG is not explicitly defined in config, so would fall back to *
        $this->assertEquals(Convert::memstring2bytes($defaultSize), $fileField->getValidator()->getAllowedMaxFileSize('png'));
    }
}
