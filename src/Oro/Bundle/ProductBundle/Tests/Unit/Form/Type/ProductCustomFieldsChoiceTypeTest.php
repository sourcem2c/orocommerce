<?php

namespace Oro\Bundle\ProductBundle\Tests\Unit\Form\Type;

use Symfony\Component\Form\Test\FormIntegrationTestCase;

use Oro\Bundle\ProductBundle\Form\Type\ProductCustomVariantFieldsChoiceType;
use Oro\Bundle\ProductBundle\Provider\CustomVariantFieldsProvider;

class ProductCustomFieldsChoiceTypeTest extends FormIntegrationTestCase
{
    /**
     * @var ProductCustomVariantFieldsChoiceType
     */
    protected $formType;

    /**
     * @var CustomVariantFieldsProvider|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $customFieldProvider;

    /**
     * @var string
     */
    protected $productClass = 'stdClass';

    /**
     * @var array
     */
    protected $exampleCustomFields = [
        'size' => [
            'name' => 'size',
            'label' => 'Size Label'
        ],
        'color' => [
            'name' => 'color',
            'label' => 'Color Label'
        ]
    ];

    /**
     * {@inheritdoc}
     */
    protected function setUp()
    {
        parent::setUp();

        $this->customFieldProvider = $this->getMockBuilder(CustomVariantFieldsProvider::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->formType = new ProductCustomVariantFieldsChoiceType($this->customFieldProvider, $this->productClass);
    }

    /**
     * @dataProvider submitProvider
     *
     * @param string $expectedData
     */
    public function testSubmit($expectedData)
    {
        $this->customFieldProvider->expects($this->once())
            ->method('getEntityCustomFields')
            ->willReturn($this->exampleCustomFields);

        $form = $this->factory->create($this->formType);

        $this->assertNull($form->getData());
        $form->submit($expectedData);
        $this->assertTrue($form->isValid());
        $this->assertEquals($expectedData, $form->getData());
    }

    /**
     * @return array
     */
    public function submitProvider()
    {
        return [
            'empty' => [
                'expectedData' => []
            ],
            'size' => [
                'expectedData' => [
                    $this->exampleCustomFields['size']['name']
                ]
            ],
            'size&color' => [
                'expectedData' => [
                    $this->exampleCustomFields['size']['name'],
                    $this->exampleCustomFields['color']['name']
                ]
            ]
        ];
    }

    public function testGetParent()
    {
        $this->assertEquals('choice', $this->formType->getParent());
    }

    public function testGetName()
    {
        $this->assertEquals(ProductCustomVariantFieldsChoiceType::NAME, $this->formType->getName());
    }
}
