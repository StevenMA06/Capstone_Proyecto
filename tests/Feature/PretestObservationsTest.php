<?php
namespace Tests\Feature;
use Tests\TestCase;
use App\Services\PretestMeasurementService;
use App\Http\Requests\StorePretestObservationRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class PretestObservationsTest extends TestCase
{
    private bool $createdTable=false;
    protected function setUp(): void
    {
        parent::setUp();
        if (!Schema::hasTable('observaciones_pretest')) {
            (require database_path('migrations/2026_10_13_000001_create_observaciones_pretest_table.php'))->up();
            $this->createdTable=true;
        }
        DB::beginTransaction();
    }
    protected function tearDown(): void
    {
        DB::rollBack();
        if ($this->createdTable) Schema::dropIfExists('observaciones_pretest');
        parent::tearDown();
    }
    private function observation(array $changes=[]): array
    {
        return array_replace(['token'=>(string)Str::uuid(),'indicador'=>'clientes','inicio'=>'2001-01-10T09:00:00','fin'=>'2001-01-10T09:10:00','interrupciones_segundos'=>120,'referencia'=>'Prueba transaccional','observacion'=>'Pausa ajena al registro','limites_confirmados'=>1],$changes);
    }
    public function test_average_uses_effective_time_excludes_exceptions_and_other_periods(): void
    {
        $service=app(PretestMeasurementService::class);
        $service->record($this->observation(),1); // 8 minutes
        $service->record($this->observation(['interrupciones_segundos'=>0]),1); // 10 minutes
        $service->record($this->observation(['excepcional'=>1]),1);
        $service->record($this->observation(['inicio'=>'2001-02-01T09:00:00','fin'=>'2001-02-01T09:10:00']),1);
        $summary=$service->summary('2001-01-01','2001-01-31','clientes');
        $this->assertSame(2,$summary['measurements']['clientes']['count']);
        $this->assertSame(9.0,$summary['measurements']['clientes']['average']);
        $this->assertSame(1,$summary['measurements']['clientes']['exceptions']);
        $this->assertArrayNotHasKey('contratos',$summary['measurements']);
    }
    public function test_form_token_prevents_duplicate_measurements(): void
    {
        $data=$this->observation(); $service=app(PretestMeasurementService::class);
        $service->record($data,1); $service->record($data,1);
        $this->assertSame(1,DB::table('observaciones_pretest')->where('token',$data['token'])->count());
    }
    public function test_unvalidated_indicators_cannot_be_recorded(): void
    {
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(PretestMeasurementService::class)->record($this->observation(['indicador'=>'reactivacion']),1);
    }
    public function test_validation_requires_evidence_confirmation_and_incident_description(): void
    {
        $data=$this->observation(['excepcional'=>1,'observacion'=>'','limites_confirmados'=>0]);
        $request=StorePretestObservationRequest::create('/indicators/pretest','POST',$data);
        $validator=Validator::make($data,$request->rules());
        $this->assertTrue($validator->fails());
        $this->assertTrue($validator->errors()->has('observacion'));
        $this->assertTrue($validator->errors()->has('limites_confirmados'));
    }
    public function test_future_observation_is_rejected(): void
    {
        $data=$this->observation(['inicio'=>'2099-01-01T09:00:00','fin'=>'2099-01-01T09:10:00']);
        $request=StorePretestObservationRequest::create('/indicators/pretest','POST',$data);
        $validator=Validator::make($data,$request->rules());
        $this->assertTrue($validator->fails());
        $this->assertTrue($validator->errors()->has('fin'));
    }
}
