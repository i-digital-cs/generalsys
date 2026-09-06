<?php

namespace Tests\Unit\Valuation;

use App\Services\Valuation\ValuationCopy;
use App\Support\AppModule;
use Tests\TestCase;

class ValuationCopyTest extends TestCase
{
    public function test_product_defaults_match_mohra_excel(): void
    {
        $defaults = ValuationCopy::defaults(AppModule::PRODUCT);

        $this->assertEquals(15, $defaults['target_b2b_count']);
        $this->assertEquals(20, $defaults['target_b2c_count']);
        $this->assertEquals(35, $defaults['target_count_total']);
        $this->assertEquals(0, $defaults['target_b2b_amount']);
        $this->assertEquals(0, $defaults['target_b2c_amount']);
        $this->assertEquals(0, $defaults['target_amount_total']);
        $this->assertEquals(0, $defaults['kpi_base']);
        $this->assertEquals(2460, $defaults['cashflow_base']);
        $this->assertSame([], $defaults['drivers']);
    }

    public function test_service_defaults_unchanged(): void
    {
        $defaults = ValuationCopy::defaults(AppModule::SERVICE);

        $this->assertEquals(40, $defaults['target_b2b_count']);
        $this->assertEquals(45, $defaults['target_count_total']);
        $this->assertEquals(2500, $defaults['kpi_base']);
    }

    public function test_product_labels_use_mohra_overlay_in_english(): void
    {
        app()->setLocale('en');

        $this->assertSame(
            'Traders goods — Mustafa',
            ValuationCopy::line(AppModule::PRODUCT, 'sections.laundry_king'),
        );
        $this->assertSame(
            'Shipping companies',
            ValuationCopy::line(AppModule::PRODUCT, 'sections.drivers'),
        );
        $this->assertSame(
            'Laundry operation — King',
            ValuationCopy::line(AppModule::SERVICE, 'sections.laundry_king'),
        );
    }

    public function test_product_hides_service_only_blocks(): void
    {
        $this->assertTrue(ValuationCopy::showDynamicDrivers(AppModule::PRODUCT));
        $this->assertFalse(ValuationCopy::showProviders(AppModule::PRODUCT));
        $this->assertFalse(ValuationCopy::showLogisticsBlock(AppModule::PRODUCT));
        $this->assertTrue(ValuationCopy::showOperationalNotesAfterSuppliers(AppModule::PRODUCT));
        $this->assertTrue(ValuationCopy::showDynamicDrivers(AppModule::SERVICE));
    }
}
